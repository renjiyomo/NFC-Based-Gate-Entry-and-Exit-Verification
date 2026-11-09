<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Detection;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\MonitoringTestCase;

class MonitoringQueryPlanTest extends MonitoringTestCase
{
    public function test_source_and_period_queries_have_indexed_plans_on_representative_isolated_rows(): void
    {
        $person = $this->subject();
        $base = $this->passage($person, '2026-11-01 01:00:00');
        $attempt = $base->attempt->getRawOriginal();
        unset($attempt['id']);
        $event = $base->getRawOriginal();
        unset($event['id']);
        $baseAttemptId = $base->scan_attempt_id;
        $clock = CarbonImmutable::parse('2026-11-01 02:00:00', 'UTC');
        // Guarded transaction inherited from DatabaseTestCase. Valid FK-owned
        // synthetic rows exercise planner selectivity; no operational data seeded.
        foreach (array_chunk(range(1, 10000), 400) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $at = $clock->addMinutes($i);
                $rows[] = [...$attempt, 'public_id' => (string) Str::uuid(), 'request_id' => (string) Str::uuid(), 'received_at' => $at->format('Y-m-d H:i:s.u'), 'review_expires_at' => $at->addSeconds(30)->format('Y-m-d H:i:s.u'), 'reviewed_at' => $at->addSecond()->format('Y-m-d H:i:s.u')];
            }
            DB::table('scan_attempts')->insert($rows);
        }
        $attempts = ScanAttempt::where('guard_id', $this->guard->id)->where('id', '>', $baseAttemptId)->orderBy('id')->get(['id', 'received_at']);
        foreach ($attempts->chunk(400) as $chunk) {
            $rows = [];
            foreach ($chunk as $a) {
                $rows[] = [...$event, 'public_id' => (string) Str::uuid(), 'scan_attempt_id' => $a->id, 'confirmed_at' => $a->received_at->addSecond()->format('Y-m-d H:i:s.u')];
            }DB::table('access_events')->insert($rows);
        }
        $this->finishTime();
        $q = app(ReportQueries::class);
        $f = new ReportFilters(['date' => '2026-11-04']);
        try {
            app(Detection::class)->run($f, 'physical', $this->admin);
            $this->fail('Detection must reject a selection exceeding 1,000 attempts');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('detection', $e->errors());
            $this->assertSame(0, AnomalyFlag::count());
        }
        $result = ['engine' => DB::selectOne('SELECT VERSION() version')->version, 'fixture_passages' => AccessEvent::count(), 'fixture_attempts' => ScanAttempt::count(), 'transaction_rollback' => true, 'queries' => []];
        foreach (['passages' => $q->events($f, 'physical')->selectRaw('COUNT(*) total'), 'attempts' => $q->attempts($f, 'physical')->selectRaw('COUNT(*) total')] as $name => $query) {
            $plan = DB::selectOne('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings());
            $json = json_decode($plan->EXPLAIN, true, 512, JSON_THROW_ON_ERROR);
            $keys = [];
            $access = [];
            $walk = function ($node) use (&$walk, &$keys, &$access) {
                if (! is_array($node)) {
                    return;
                }foreach ($node as $key => $value) {
                    if ($key === 'key') {
                        $keys[] = $value;
                    }if ($key === 'access_type') {
                        $access[] = $value;
                    }if (is_array($value)) {
                        $walk($value);
                    }
                }
            };
            $walk($json);
            $this->assertContains($name === 'passages' ? 'event_source_clock' : 'attempt_source_clock', $keys);
            $this->assertContains('range', $access);
            $started = microtime(true);
            $count = $query->first()->total;
            $result['queries'][$name] = ['keys' => $keys, 'access' => $access, 'matching_rows' => (int) $count, 'elapsed_ms' => round((microtime(true) - $started) * 1000, 3)];
        }
        file_put_contents(base_path('.tools/artifacts/phase-7-query-plans.json'), json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
