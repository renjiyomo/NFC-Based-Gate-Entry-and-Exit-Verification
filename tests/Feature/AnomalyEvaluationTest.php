<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\GuardGateAssignment;
use App\Models\NfcCredential;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Rules;
use App\Services\GateOperations\GateOperations;
use Illuminate\Support\Str;
use Tests\MonitoringTestCase;

class AnomalyEvaluationTest extends MonitoringTestCase
{
    public function test_labeled_synthetic_rule_scenarios_and_documented_false_alarm(): void
    {
        config(['anomalies.threshold_rules' => true, 'anomalies.cross_gate_enabled' => true]);
        $rules = app(Rules::class);
        $policy = $rules->policy();
        $rows = [];
        $score = function (string $label, string $code, ScanAttempt $a, bool $expected, ?array $p = null) use (&$rows, $rules, $policy): void {
            $predicted = array_key_exists($code, $rules->evaluate($a, $p ?? $policy));
            $rows[] = ['scenario' => $label, 'rule' => $code, 'expected_pattern' => $expected, 'detected' => $predicted];
            $this->assertSame($expected, $predicted, $label);
        };
        $person = $this->subject();
        $first = $this->passage($person, '2026-11-09 00:00:00');
        $second = $this->passage($person, '2026-11-09 00:01:00');
        $exit = $this->passage($person, '2026-11-09 00:02:00', 'exit');
        $repeatExit = $this->passage($person, '2026-11-09 00:03:00', 'exit');
        $score('First recorded Entry', 'repeated_entry', $first->attempt, false);
        $score('Entry follows Entry', 'repeated_entry', $second->attempt, true);
        $score('Exit follows Entry', 'exit_without_entry', $exit->attempt, false);
        $score('Exit follows Exit', 'exit_without_entry', $repeatExit->attempt, true);
        $other = $this->subject();
        $initialExit = $this->passage($other, '2026-11-09 00:04:00', 'exit');
        $score('Exit with no earlier passage', 'exit_without_entry', $initialExit->attempt, true);
        $hours = [...$policy, 'open_hour' => 6, 'close_hour' => 20];
        $score('Daytime is within configured hours', 'after_hours', $first->attempt, false, $hours);
        $late = $this->passage($other, '2026-11-09 12:00:00');
        $score('Closing hour is outside daily interval', 'after_hours', $late->attempt, true, $hours);
        $score('Hours disabled without campus policy', 'after_hours', $late->attempt, false);
        $opening = $this->passage($other, '2026-11-09 22:00:00');
        $score('Opening hour included', 'after_hours', $opening->attempt, false, $hours);
        $score('Overnight schedule includes 20:00', 'after_hours', $late->attempt, false, [...$policy, 'open_hour' => 18, 'close_hour' => 6]);
        $originalGate = $this->gate;
        $gate = Gate::create(['code' => 'RULE-'.Str::random(8), 'name' => 'Synthetic second gate', 'status' => 'active']);
        GuardGateAssignment::create(['guard_id' => $this->guard->id, 'gate_id' => $gate->id, 'assigned_by' => $this->admin->id, 'starts_at' => now()->subYear(), 'ends_at' => now()->addYear()]);
        foreach ([29 => true, 30 => false] as $seconds => $expected) {
            $p = $this->subject();
            $this->gate = $originalGate;
            $this->passage($p, '2026-11-09 02:00:00');
            $this->gate = $gate;
            $e = $this->passage($p, '2026-11-09 02:00:'.$seconds);
            $score('Different gates separated by '.$seconds.' seconds', 'cross_gate', $e->attempt, $expected);
        }
        $p = $this->subject();
        $this->passage($p, '2026-11-09 03:00:00');
        $same = $this->passage($p, '2026-11-09 03:00:20');
        $score('Same gate is not cross-gate activity', 'cross_gate', $same->attempt, false);
        $p = $this->subject();
        $this->passage($p, '2026-11-09 04:00:00');
        for ($i = 1; $i <= 5; $i++) {
            $s = $this->scanPerson($p, '2026-11-09 04:00:0'.$i);
            $a = ScanAttempt::where('public_id', $s['id'])->sole();
            app(GateOperations::class)->review($this->guard, $this->gate, $a, ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'report-guard', 'physical');
            if (in_array($i, [4, 5], true)) {
                $score($i.' suppressed confirmations', 'duplicate_burst', $a->fresh(), $i === 5);
            }
        }
        $p = $this->subject();
        NfcCredential::where('public_id', $this->receipts[$p->id]['credential']['id'])->sole()->forceFill(['status' => 'revoked'])->save();
        for ($i = 1; $i <= 5; $i++) {
            $s = $this->scanPerson($p, '2026-11-09 05:00:0'.$i);
            $a = ScanAttempt::where('public_id', $s['id'])->sole();
            if (in_array($i, [2, 3], true)) {
                $score($i.' uses of revoked credential', 'revoked_burst', $a, $i === 3);
            }
            if (in_array($i, [4, 5], true)) {
                $score($i.' denied attempts at gate', 'denied_burst', $a, $i === 5);
            }
        }
        $tp = count(array_filter($rows, fn ($r) => $r['expected_pattern'] && $r['detected']));
        $tn = count(array_filter($rows, fn ($r) => ! $r['expected_pattern'] && ! $r['detected']));
        $fp = count(array_filter($rows, fn ($r) => ! $r['expected_pattern'] && $r['detected']));
        $fn = count(array_filter($rows, fn ($r) => $r['expected_pattern'] && ! $r['detected']));
        file_put_contents(base_path('.tools/artifacts/anomaly-evaluation.json'), json_encode(['scope' => 'Synthetic recording-pattern labels, not misconduct or campus effectiveness', 'scenarios' => $rows, 'confusion_matrix' => compact('tp', 'tn', 'fp', 'fn'), 'precision' => $tp + $fp ? $tp / ($tp + $fp) : null, 'recall' => $tp + $fn ? $tp / ($tp + $fn) : null, 'false_positive_rate' => $fp + $tn ? $fp / ($fp + $tn) : null, 'false_alarm_analysis' => 'A deliberately absent initial Entry still triggers the Exit rule. Human review can mark an authorized test exception as false_alarm; rule compliance does not measure whether an activity was improper.', 'transaction_rollback' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
