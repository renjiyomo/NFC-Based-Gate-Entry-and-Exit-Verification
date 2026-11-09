<?php

namespace App\Services\Summaries;

use App\Models\AnomalyFlag;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;

class Aggregates
{
    public const VERSION = 'aggregate-facts-v1';

    public const LIMITATIONS = [
        'presence_estimate' => 'Recorded presence is an estimate; missing scans and guard observations limit it.',
        'review_indicators' => 'Review flags do not establish wrongdoing. Detection runs on demand and may not cover every record.',
        'not_attendance' => 'Gate activity does not establish classroom attendance or continuous presence.',
    ];

    public function build(ReportFilters $f, string $source): array
    {
        $s = app(ReportQueries::class)->summary($f, $source);
        $facts = [];
        $add = function (string $id, int $count, string $sentence) use (&$facts): void {
            if ($count === 1) {
                $sentence = str_replace(['passages were', 'people had', 'attempts were', 'confirmations were', 'people were', 'flags relate', 'flags have'], ['passage was', 'person had', 'attempt was', 'confirmation was', 'person was', 'flag relates', 'flag has'], $sentence);
            }
            $facts[$id] = ['count' => $count, 'sentence' => $count.' '.$sentence];
        };
        $add('entries', $s['entries'], 'effective Entry passages were recorded.');
        $add('exits', $s['exits'], 'effective Exit passages were recorded.');
        $add('passages', $s['passages'], 'effective confirmed passages were recorded in total.');
        $add('people', $s['unique_people'], 'distinct people had effective confirmed passages.');
        $add('attempts', $s['attempts'], 'verification attempts were received.');
        $add('denied', $s['denied'], 'verification attempts were denied; these did not create passages.');
        $add('duplicates', $s['duplicates'], 'duplicate passage confirmations were suppressed.');
        $add('voided', $s['voided'], 'original passages were excluded by correction decisions.');
        $add('inside', $s['presence']['inside'], 'retained people were last recorded inside at the cutoff across all gates; this is an estimate.');
        $flags = AnomalyFlag::where('source', $source)->where('occurred_at', '>=', $f->from->format('Y-m-d H:i:s.u'))->where('occurred_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('occurred_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'));
        $add('flags', (clone $flags)->count(), 'saved review flags relate to attempts in this period; flags are not confirmed incidents.');
        foreach (['open', 'in_review', 'resolved', 'false_alarm'] as $status) {
            $q = clone $flags;
            $q->where(function ($q) use ($status): void {
                $q->whereHas('latestReview', fn ($v) => $v->where('status', $status));
                if ($status === 'open') {
                    $q->orWhereDoesntHave('latestReview');
                }
            });
            $add('flags_'.$status, $q->count(), 'saved review flags have the current status '.str_replace('_', ' ', $status).'.');
        }
        // Never pass names, campus numbers, gate/guard names, references or notes.
        // Gate aliases are scoped to this snapshot; they do not identify stations externally.
        foreach (array_slice($s['gates'], 0, 10) as $i => $gate) {
            $alias = 'Gate '.($i + 1);
            $n = $i + 1;
            $add('gate_'.$n.'_entries', $gate['entries'], 'effective Entry passages were recorded at '.$alias.'.');
            $add('gate_'.$n.'_exits', $gate['exits'], 'effective Exit passages were recorded at '.$alias.'.');
        }

        return ['contract' => self::VERSION, 'period' => ['from' => $f->values['from'], 'to' => $f->values['to'], 'timezone' => 'Asia/Manila'], 'facts' => $facts, 'limitations' => self::LIMITATIONS, 'gate_coverage' => count($s['gates']) > 10 ? 'First 10 gates with effective activity; see full reports for every gate.' : 'All gates with effective activity in this selection.'];
    }

    public function hash(array $snapshot): string
    {
        $sort = function (array $v) use (&$sort): array {
            if (! array_is_list($v)) {
                ksort($v);
            }foreach ($v as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $v;
        };

        return hash('sha256', json_encode($sort($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function validate(array $output, array $snapshot): array
    {
        if (array_diff(array_keys($output), ['fact_ids', 'limitation_ids']) || ! isset($output['fact_ids'],$output['limitation_ids']) || ! is_array($output['fact_ids']) || ! array_is_list($output['fact_ids']) || ! is_array($output['limitation_ids']) || ! array_is_list($output['limitation_ids'])) {
            throw new \UnexpectedValueException('invalid_structure');
        }
        $ids = $output['fact_ids'];
        $limits = $output['limitation_ids'];
        if (array_filter($ids, fn ($v) => ! is_string($v)) || array_filter($limits, fn ($v) => ! is_string($v))) {
            throw new \UnexpectedValueException('invalid_facts');
        }
        if (count($ids) < 5 || count($ids) > 12 || count($ids) !== count(array_unique($ids)) || array_diff($ids, array_keys($snapshot['facts'])) || array_diff(['entries', 'exits', 'attempts', 'denied', 'flags'], $ids) || count($limits) !== count(self::LIMITATIONS) || array_diff(array_keys(self::LIMITATIONS), $limits)) {
            throw new \UnexpectedValueException('invalid_facts');
        }

        return ['ids' => $ids, 'sentences' => [...array_map(fn ($id) => $snapshot['facts'][$id]['sentence'], $ids), ...array_values(self::LIMITATIONS)]];
    }
}
