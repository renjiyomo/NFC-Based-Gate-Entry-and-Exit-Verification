<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ReportTable
{
    public function build(ReportFilters $f, string $source, string $dataset, int $limit): array
    {
        $queries = app(ReportQueries::class);
        if ($dataset === 'summary') {
            $s = $queries->summary($f, $source);
            $rows = [['Summary', 'Effective confirmed passages', $s['entries'], $s['exits'], $s['passages']], ['Summary', 'Unique people with passage', '', '', $s['unique_people']], ['Checks', 'Verification attempts', '', '', $s['attempts']], ['Checks', 'Denied attempts', '', '', $s['denied']], ['Checks', 'Duplicate passages suppressed', '', '', $s['duplicates']], ['Corrections', 'Voided original passages', '', '', $s['voided']], ['Presence estimate', 'Last recorded inside, campus-wide', '', '', $s['presence']['inside']], ['Presence estimate', 'Last recorded outside, campus-wide', '', '', $s['presence']['outside']], ['Presence estimate', 'People with no effective passage', '', '', $s['presence']['unknown']]];
            foreach ($s['trend'] as $r) {
                $rows[] = ['Daily', $r['date'], $r['entries'], $r['exits'], $r['entries'] + $r['exits']];
            }
            foreach (['gates' => 'Gate', 'kinds' => 'Person type'] as $key => $section) {
                foreach ($s[$key] as $r) {
                    $rows[] = [$section, $r['label'], $r['entries'], $r['exits'], $r['entries'] + $r['exits']];
                }
            }
            foreach (['hourly' => 'Hour (Manila)', 'denied_reasons' => 'Denied reason'] as $key => $section) {
                foreach ($s[$key] as $r) {
                    $rows[] = [$section, $r['label'], '', '', $r['value']];
                }
            }

            if (count($rows) > $limit) {
                throw ValidationException::withMessages(['export' => "This summary exceeds {$limit} rows. Narrow the period or filters, or use CSV/Excel."]);
            }

            return ['headers' => ['Section', 'Measure', 'Entries', 'Exits', 'Total'], 'rows' => $rows];
        }
        $details = new ReportFilters([...$f->values, 'view' => $dataset], $f->asOf);
        $query = $queries->history($details, $source);
        if ((clone $query)->reorder()->count() > $limit) {
            throw ValidationException::withMessages(['export' => "This export exceeds {$limit} rows. Narrow the period or filters; no rows were silently omitted."]);
        }
        $rows = $query->get()->map(function ($r) use ($details): array {
            $d = HistoryData::row($r, $details);

            return [CarbonImmutable::parse($d['time'])->setTimezone('Asia/Manila')->format('Y-m-d H:i:s.u'), $d['person']['name'] ?? 'Unidentified', $d['person']['number'] ?? '', $d['person']['kind'] ?? '', $d['gate']['code'], $d['guard'], $d['direction'], $d['outcome'], $d['reason'], $d['review'], $d['review_reason'] ?? '', $d['voided'] ? 'Voided' : ($d['passage_id'] ? 'Effective passage' : 'No passage'), $d['credential'] ?? '', $d['sequence_notice'] ?? ''];
        })->all();

        return ['headers' => ['Time (Asia/Manila)', 'Person', 'Campus number', 'Person type', 'Gate', 'Guard', 'Direction', 'Verification', 'Reason', 'Review', 'Review reason', 'Passage state', 'Credential label', 'Sequence notice'], 'rows' => $rows];
    }
}
