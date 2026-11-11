<?php

namespace App\Services\Anomalies;

use App\Models\AccessEvent;
use App\Models\ScanAttempt;

class Rules
{
    public const TITLES = [
        'missing_exit' => 'Missing Exit',
        'rapid_scans' => 'Repeated scans within one minute',
        'revoked_use' => 'Revoked coin presented',
        'unavailable_credential' => 'Unavailable access presented',
        'repeated_entry' => 'Entry follows a recorded Entry',
        'exit_without_entry' => 'Exit without a preceding recorded Entry',
        'denied_burst' => 'Repeated denied checks at one gate',
        'revoked_burst' => 'Repeated use of a revoked credential',
        'duplicate_burst' => 'Repeated suppressed passages',
        'cross_gate' => 'Short interval between different gates',
        'after_hours' => 'Passage outside configured hours',
    ];

    public function policy(): array
    {
        return ['algorithm' => 2, 'threshold_rules' => (bool) config('anomalies.threshold_rules'),
            'window_seconds' => (int) config('anomalies.window_seconds'), 'denied_count' => (int) config('anomalies.denied_count'),
            'revoked_count' => (int) config('anomalies.revoked_count'), 'duplicate_count' => (int) config('anomalies.duplicate_count'),
            'cross_gate_seconds' => (int) config('anomalies.cross_gate_seconds'),
            'rapid_count' => (int) config('anomalies.rapid_count'),
            'credential_alerts' => (bool) config('anomalies.credential_alerts'),
            'cross_gate_enabled' => (bool) config('anomalies.cross_gate_enabled'),
            'open_hour' => config('anomalies.open_hour'), 'close_hour' => config('anomalies.close_hour')];
    }

    public function version(array $policy): string
    {
        ksort($policy);

        return hash('sha256', json_encode($policy, JSON_THROW_ON_ERROR));
    }

    /** Deterministic candidates. No guard decisions, identity inference or record mutation. */
    public function evaluate(ScanAttempt $a, array $p): array
    {
        $found = [];
        $add = function (string $code, array $ids, string $why, string $severity = 'low') use (&$found): void {
            $ids = array_values(array_unique($ids));
            sort($ids);
            $found[$code] = ['ids' => $ids, 'explanation' => $why, 'severity' => $severity];
        };
        $event = AccessEvent::effective()->where('scan_attempt_id', $a->id)->first();
        if ($event) {
            $clock = $event->confirmed_at->format('Y-m-d H:i:s.u');
            $previous = AccessEvent::effective()->where('source', $a->source)->where('person_id', $a->person_id)
                ->where(fn ($q) => $q->where('confirmed_at', '<', $clock)->orWhere(fn ($t) => $t->where('confirmed_at', $clock)->where('id', '<', $event->id)))
                ->orderByDesc('confirmed_at')->orderByDesc('id')->first();
            if ($event->direction === 'entry' && $previous?->direction === 'entry' && ! str_starts_with($event->policy_version, 'gate-state-v1-')) {
                $add('repeated_entry', [$previous->scan_attempt_id, $a->id], 'The preceding effective passage for this person was also Entry. A missing Exit or a recording error may explain this; it does not establish misconduct.');
            }
            if ($event->direction === 'exit' && $previous?->direction !== 'entry') {
                $add('exit_without_entry', array_filter([$previous?->scan_attempt_id, $a->id]), 'No immediately preceding effective Entry is recorded for this Exit. Earlier missing scans or incomplete coverage may explain this.');
            }
            if ($p['threshold_rules'] && ($p['cross_gate_enabled'] ?? true) && $previous && $previous->gate_id !== $event->gate_id && $previous->confirmed_at->diffInMicroseconds($event->confirmed_at) < $p['cross_gate_seconds'] * 1000000) {
                $add('cross_gate', [$previous->scan_attempt_id, $a->id], 'Two effective passages for this person occurred at different gates less than '.$p['cross_gate_seconds'].' seconds apart. Gate distance is not known; review recording and timing context.');
            }
            if ($p['open_hour'] !== null && $p['close_hour'] !== null) {
                $hour = (int) $event->confirmed_at->setTimezone('Asia/Manila')->format('G');
                $inside = $p['open_hour'] < $p['close_hour'] ? $hour >= $p['open_hour'] && $hour < $p['close_hour'] : $hour >= $p['open_hour'] || $hour < $p['close_hour'];
                if (! $inside) {
                    $add('after_hours', [$a->id], 'This effective passage falls outside configured daily Manila hours. Approved exceptions and shift work require human review.');
                }
            }
        }
        if (! $p['threshold_rules']) {
            return $found;
        }
        $automatic = str_starts_with($a->policy_version, 'gate-state-v1-');
        if ($automatic && ($p['credential_alerts'] ?? false) && $a->outcome === 'denied') {
            if ($a->reason === 'revoked' && $a->credential_id) {
                $add('revoked_use', [$a->id], 'A revoked coin was presented at this gate. Check the credential history and assist the holder with the current coin.', 'medium');
            } elseif (in_array($a->reason, ['expired', 'person_inactive', 'not_selected', 'cancelled', 'pending'], true)) {
                $add('unavailable_credential', [$a->id], 'This scan was rejected because the credential or person record is unavailable for access. Review the saved rejection reason.');
            }
        }
        if ($automatic && $a->person_id && $a->outcome === 'allowed' && ($p['rapid_count'] ?? 0) >= 3) {
            $ids = ScanAttempt::where('source', $a->source)->where('gate_id', $a->gate_id)->where('person_id', $a->person_id)->where('outcome', 'allowed')
                ->where('received_at', '>', $a->received_at->subSeconds($p['window_seconds'])->format('Y-m-d H:i:s.u'))
                ->where(fn ($q) => $q->where('received_at', '<', $a->received_at->format('Y-m-d H:i:s.u'))->orWhere(fn ($q) => $q->where('received_at', $a->received_at->format('Y-m-d H:i:s.u'))->where('id', '<=', $a->id)))
                ->orderByDesc('received_at')->orderByDesc('id')->limit($p['rapid_count'])->pluck('id')->all();
            if (count($ids) === $p['rapid_count']) {
                $add('rapid_scans', $ids, $p['rapid_count'].' separate registered-coin scans were received for this person at this gate within '.$p['window_seconds'].' seconds. Check for repeated tapping, quick returns or reader issues.');
            }
        }
        foreach (['denied_burst' => $p['denied_count'], 'revoked_burst' => $p['revoked_count'], 'duplicate_burst' => $p['duplicate_count']] as $code => $threshold) {
            if ($automatic && $code === 'revoked_burst' && ($p['credential_alerts'] ?? false)) {
                continue;
            }
            if ($automatic && $code === 'duplicate_burst' && ($p['rapid_count'] ?? 0) >= 3) {
                continue;
            }
            if (($code === 'denied_burst' && $a->outcome !== 'denied') || ($code === 'revoked_burst' && ($a->reason !== 'revoked' || ! $a->credential_id)) || ($code === 'duplicate_burst' && (! $a->duplicate_of_event_id || ! $a->person_id))) {
                continue;
            }
            $q = ScanAttempt::where('source', $a->source)->where('gate_id', $a->gate_id)
                ->where('received_at', '>', $a->received_at->subSeconds($p['window_seconds'])->format('Y-m-d H:i:s.u'))
                ->where(fn ($t) => $t->where('received_at', '<', $a->received_at->format('Y-m-d H:i:s.u'))->orWhere(fn ($s) => $s->where('received_at', $a->received_at->format('Y-m-d H:i:s.u'))->where('id', '<=', $a->id)));
            if ($code === 'denied_burst') {
                $q->where('outcome', 'denied');
            }
            if ($code === 'revoked_burst') {
                $q->where('reason', 'revoked')->where('credential_id', $a->credential_id);
            }
            if ($code === 'duplicate_burst') {
                $q->whereNotNull('duplicate_of_event_id')->where('person_id', $a->person_id);
            }
            // Keep only threshold witnesses: one deterministic flag per triggering anchor,
            // reruns are deduplicated; additional qualifying anchors remain separate.
            $ids = $q->orderByDesc('received_at')->orderByDesc('id')->limit($threshold)->pluck('id')->all();
            if (count($ids) === $threshold) {
                $add($code, $ids, "At least {$threshold} qualifying attempts occurred at this gate in the preceding {$p['window_seconds']} seconds (lower boundary excluded). ".($code === 'denied_burst' ? 'This is a station-level pattern; unknown references are not attributed to one person.' : 'Review reader errors, repeated taps and operator context before drawing conclusions.'), 'medium');
            }
        }

        return $found;
    }
}
