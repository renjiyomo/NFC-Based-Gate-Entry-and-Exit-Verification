<?php

namespace Tests\Postgres;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Detection;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

class WorkflowTest extends PostgresTestCase
{
    public function test_automatic_single_gate_flags_are_append_only_and_idempotent(): void
    {
        config(['anomalies.threshold_rules' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-11-11T01:00:00Z'));
        $f = $this->fixture();
        $service = app(AutomaticPassages::class);
        $data = ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload']];
        $service->automaticScan($f['guard'], $f['gate'], $data, 'activity-session', 'physical');
        foreach ([11, 22, 33] as $seconds) {
            $this->travelTo(CarbonImmutable::parse('2026-11-11T01:00:00Z')->addSeconds($seconds));
            $service->automaticScan($f['guard'], $f['gate'], [...$data, 'request_id' => (string) Str::uuid()], 'activity-session', 'physical');
        }
        $flag = AnomalyFlag::where('rule_code', 'rapid_scans')->sole();
        $this->assertSame(3, $flag->evidence()->count());
        $this->assertSame(4, AccessEvent::count());
        $this->assertSame(4, ScanAttempt::count());
        $this->assertSame(0, app(Detection::class)->run(new ReportFilters(['date' => '2026-11-11']), 'physical', $this->admin)['created']);
        app(CredentialLifecycle::class)->terminate($this->admin, $f['credential']->fresh(), 'revoke', 'lost');
        $result = $service->automaticScan($f['guard'], $f['gate'], [...$data, 'request_id' => (string) Str::uuid()], 'activity-session', 'physical');
        $this->assertNull($result['passage']);
        $this->assertSame(1, AnomalyFlag::where('rule_code', 'revoked_use')->count());
    }

    public function test_binary_credentials_and_automatic_scan_idempotency_duplicates_exit_and_recovery(): void
    {
        $this->travelTo(CarbonImmutable::now('UTC'));
        $f = $this->fixture();
        $this->assertSame(32, strlen($f['credential']->fresh()->token_digest));
        $this->assertSame(32, DB::selectOne('SELECT octet_length(token_digest) AS size FROM nfc_credentials WHERE id = ?', [$f['credential']->id])->size);
        $service = app(AutomaticPassages::class);
        $data = ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload'], 'direction' => 'entry'];
        $first = $service->scan($f['guard'], $f['gate'], $data, 'gate-session', 'physical');
        $same = $service->scan($f['guard'], $f['gate'], $data, 'gate-session', 'physical');
        $duplicate = $service->scan($f['guard'], $f['gate'], [...$data, 'request_id' => (string) Str::uuid()], 'gate-session', 'physical');
        $this->assertSame($first['passage']['id'], $same['passage']['id']);
        $this->assertNull($duplicate['passage']);
        $this->assertSame('duplicate_passage', $duplicate['review']['reason']);
        $this->assertSame($first['passage']['id'], $service->recover($f['guard'], $f['gate'], $data['request_id'], 'gate-session', 'physical')['passage']['id']);
        $exit = $service->scan($f['guard'], $f['gate'], [...$data, 'request_id' => (string) Str::uuid(), 'direction' => 'exit'], 'gate-session', 'physical');
        $this->assertSame('exit', $exit['passage']['direction']);
        $this->assertSame(3, ScanAttempt::count());
        $this->assertSame(2, AccessEvent::count());
        app(CredentialLifecycle::class)->terminate($this->admin, $f['credential']->fresh(), 'revoke', 'lost');
        $denied = $service->scan($f['guard'], $f['gate'], [...$data, 'request_id' => (string) Str::uuid()], 'gate-session', 'physical');
        $this->assertSame('denied', $denied['verification']['outcome']);
        $this->assertNull($denied['identity']);
        $this->assertSame(2, AccessEvent::count());
    }

    public function test_database_permissions_allow_review_columns_and_reject_fact_history_ddl_catalogue_and_role_changes(): void
    {
        $f = $this->fixture();
        app(AutomaticPassages::class)->scan($f['guard'], $f['gate'], ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload'], 'direction' => 'entry'], 'session', 'physical');
        $this->assertSame(1, DB::update('UPDATE scan_attempts SET review_reason=review_reason'));
        foreach (['UPDATE scan_attempts SET reason=reason', 'UPDATE access_events SET direction=direction', 'DELETE FROM access_events', 'INSERT INTO campus_departments (name) VALUES (\'Synthetic forbidden department\')', 'CREATE TABLE forbidden_test (id integer)', 'SET ROLE postgres', 'SELECT id FROM auth.users LIMIT 1'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('Restricted database operation succeeded.');
            } catch (QueryException $error) {
                $this->assertSame('42501', $error->errorInfo[0]);
            }
        }
        $this->assertSame(1, AccessEvent::count());
    }

    public function test_manila_reports_corrections_and_presence_reconcile_without_rewriting_passages(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T15:59:40Z'));
        $f = $this->fixture();
        $service = app(AutomaticPassages::class);
        $service->scan($f['guard'], $f['gate'], ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload'], 'direction' => 'entry'], 'session', 'physical');
        $this->travel(30)->seconds();
        $service->scan($f['guard'], $f['gate'], ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload'], 'direction' => 'exit'], 'session', 'physical');
        $reports = app(ReportQueries::class);
        $filters = new ReportFilters(['period' => 'daily', 'date' => '2026-11-11']);
        $summary = $reports->summary($filters, 'physical');
        $this->assertSame(0, $summary['entries']);
        $this->assertSame(1, $summary['exits']);
        $this->assertSame(1, $summary['hourly'][0]['value']);
        $this->assertSame(1, $summary['presence']['outside']);
        $event = AccessEvent::where('direction', 'exit')->sole();
        app(PassageCorrections::class)->append($this->admin, $event, ['action' => 'void', 'reason' => 'Synthetic correction check', 'expected_revision' => 0], 'physical');
        $corrected = $reports->summary($filters, 'physical');
        $this->assertSame(0, $corrected['exits']);
        $this->assertSame(1, $corrected['presence']['inside']);
        $this->assertSame(2, AccessEvent::count());
        $this->assertSame(1, $reports->directoryCounts('physical')['students']);
    }

    public function test_shared_database_cache_increment_and_locks_work_across_store_instances(): void
    {
        config(['cache.stores.database.lock_table' => 'cache_locks']);
        $store = Cache::store('database');
        $key = 'pg-test-'.Str::uuid();
        $store->put($key, 1, 60);
        $store->increment($key);
        $this->assertSame(2, $store->get($key));
        $first = $store->lock($key.'-lock', 10);
        $second = $store->lock($key.'-lock', 10);
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $first->release();
        $this->assertTrue($second->get());
        $second->release();
    }

    public function test_database_session_payload_stays_encrypted(): void
    {
        $store = app('session')->driver('database');
        $store->start();
        $store->put('synthetic-marker', 'private-value');
        $store->save();
        $payload = DB::table('sessions')->where('id', $store->getId())->value('payload');
        $this->assertStringNotContainsString('private-value', base64_decode($payload));
        $this->assertStringContainsString('private-value', app('encrypter')->decrypt(base64_decode($payload), false));
        $store->invalidate();
    }

    public function test_admin_directory_and_gate_http_routes_use_the_postgres_connection(): void
    {
        $f = $this->fixture();
        $this->get('/admin/students?q=synthetic')->assertOk();
        $this->get('/admin/dashboard')->assertOk();
        $this->signIn($f['guard']);
        $this->get('/admin/students')->assertForbidden();
        $this->postJson('/guard/gates/'.$f['gate']->id.'/automatic-scan', ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload']])->assertOk()->assertJsonPath('passage.direction', 'entry');
    }
}
