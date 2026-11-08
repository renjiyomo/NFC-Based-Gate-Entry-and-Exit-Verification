<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AuditLog;
use App\Models\PassageCorrection;
use App\Models\ScanAttempt;
use App\Services\Credentials\CredentialToken;
use App\Services\GateOperations\GateOperations;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\MonitoringTestCase;

class MonitoringTest extends MonitoringTestCase
{
    public function test_manila_boundaries_totals_and_zero_filled_trends_reconcile(): void
    {
        $student = $this->subject();
        $employee = $this->subject('employees');
        $this->passage($student, '2026-11-08 15:59:59.999999', 'exit');
        $this->passage($student, '2026-11-08 16:00:00');
        $this->passage($student, '2026-11-09 15:59:59.999999', 'exit');
        $this->passage($student, '2026-11-09 16:00:00');
        $this->passage($employee, '2026-11-09 02:00:00');
        $repeat = $this->scanPerson($employee, '2026-11-09 02:00:06');
        app(GateOperations::class)->review($this->guard, $this->gate, ScanAttempt::where('public_id', $repeat['id'])->sole(), ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'report-guard', 'physical');
        app(GateOperations::class)->scan($this->guard, $this->gate, ['payload' => app(CredentialToken::class)->generate(), 'request_id' => (string) Str::uuid(), 'direction' => 'entry'], 'report-guard', 'physical');
        $this->finishTime();
        $f = new ReportFilters(['date' => '2026-11-09']);
        $s = app(ReportQueries::class)->summary($f, 'physical');
        $this->assertSame([2, 1, 3, 1, 1], [$s['entries'], $s['exits'], $s['passages'], $s['denied'], $s['duplicates']]);
        $this->assertSame([['date' => '2026-11-09', 'entries' => 2, 'exits' => 1]], $s['trend']);
        $this->assertSame(3, array_sum(array_column($s['hourly'], 'value')));
        $this->assertSame(3, array_sum(array_map(fn ($r) => $r['entries'] + $r['exits'], $s['kinds'])));
        $this->get('/admin/dashboard?date=2026-11-09')->assertOk()->assertInertia(fn (Assert $p) => $p->component('monitoring/Index')->where('summary.passages', 3)->where('summary.duplicates', 1)->where('period.timezone', 'Asia/Manila'));
    }

    public function test_weekly_monthly_custom_periods_and_invalid_inputs(): void
    {
        $week = new ReportFilters(['period' => 'weekly', 'date' => '2026-11-09']);
        $this->assertSame('2026-11-09', $week->values['from']);
        $this->assertSame('2026-11-15', $week->values['to']);
        $month = new ReportFilters(['period' => 'monthly', 'date' => '2026-11-09']);
        $this->assertSame('2026-10-31 16:00:00', $month->from->format('Y-m-d H:i:s'));
        $this->getJson('/admin/reports?period=custom&from=2025-01-01&to=2026-11-09')->assertUnprocessable();
        $this->getJson('/admin/history?source=simulated')->assertUnprocessable();
        $this->getJson('/admin/history?period=custom&from=2026-11-10&to=2026-11-09')->assertUnprocessable();
        $this->getJson('/admin/history?per_page=999999')->assertUnprocessable();
    }

    public function test_corrections_recalculate_history_presence_and_preserve_originals(): void
    {
        $person = $this->subject();
        $entry = $this->passage($person, '2026-11-08 16:00:00');
        $exit = $this->passage($person, '2026-11-09 01:00:00', 'exit');
        $original = $exit->getRawOriginal();
        $this->finishTime();
        $service = app(PassageCorrections::class);
        $void = $service->append($this->admin, $exit, ['action' => 'void', 'reason' => 'Synthetic correction for inaccurate lab exit', 'expected_revision' => 0], 'physical');
        $f = new ReportFilters(['date' => '2026-11-09']);
        $q = app(ReportQueries::class);
        $s = $q->summary($f, 'physical');
        $this->assertSame([1, 0, 1, 1], [$s['entries'], $s['exits'], $s['voided'], $s['presence']['inside']]);
        $this->assertSame($original, $exit->fresh()->getRawOriginal());
        $this->get('/admin/history/passages/'.$exit->public_id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('record.voided', true)->has('corrections.data', 1));
        $this->post('/admin/history/passages/'.$exit->public_id.'/corrections', ['action' => 'reinstate', 'reason' => 'Stale page must not append', 'expected_revision' => 0])->assertSessionHasErrors('expected_revision');
        $note = $service->append($this->admin, $exit, ['action' => 'note', 'reason' => 'Synthetic supporting explanation', 'expected_revision' => $void->id], 'physical');
        $this->assertSame(1, $q->summary($f, 'physical')['voided']);
        $service->append($this->admin, $exit, ['action' => 'reinstate', 'reason' => 'Synthetic reinstatement after review', 'expected_revision' => $note->id], 'physical');
        $this->assertSame([1, 0], [$q->summary($f, 'physical')['exits'], $q->summary($f, 'physical')['presence']['inside']]);
        $this->assertSame(2, AccessEvent::count());
        $this->assertSame(3, PassageCorrection::count());
        $this->assertSame(3, AuditLog::where('action', 'passage.correction_added')->count());
        try {
            DB::table('passage_corrections')->where('id', $void->id)->update(['reason' => 'rewrite']);
            $this->fail('Runtime correction UPDATE allowed');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[1], [1142, 1143]);
        }
    }

    public function test_source_isolation_role_authorization_and_no_sensitive_props(): void
    {
        $real = $this->subject();
        $sim = $this->subject('employees', 'simulated');
        $event = $this->passage($real, '2026-11-09 01:00:00');
        config(['nfcheck.nfc_simulator' => true]);
        $simEvent = $this->passage($sim, '2026-11-09 02:00:00', 'entry', 'simulated');
        config(['nfcheck.nfc_simulator' => false]);
        $this->finishTime();
        $response = $this->get('/admin/history?date=2026-11-09');
        $response->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.id', $event->public_id));
        foreach ($this->receipts as $r) {
            $this->assertStringNotContainsString($r['payload'], $response->getContent());
            $this->assertStringNotContainsString($r['completion_nonce'], $response->getContent());
        }
        $this->get('/admin/history/passages/'.$simEvent->public_id)->assertNotFound();
        $this->signIn($this->guard);
        foreach (['/admin/dashboard', '/admin/history', '/admin/analytics', '/admin/reports', '/admin/history/passages/'.$event->public_id, '/admin/reports/export?format=csv&dataset=summary'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/history/passages/'.$event->public_id.'/corrections', ['action' => 'void', 'reason' => 'Guard cannot correct', 'expected_revision' => 0])->assertForbidden();
    }

    public function test_filters_and_pagination_preserve_archived_identity_history(): void
    {
        $p = $this->subject('students', 'physical', ['full_name' => 'Literal %_! student']);
        $other = $this->subject('employees');
        $this->passage($p, '2026-11-09 01:00:00');
        $this->passage($other, '2026-11-09 02:00:00');
        for ($i = 0; $i < 25; $i++) {
            $this->scanPerson($p, '2026-11-09 03:'.sprintf('%02d', $i).':00');
        }
        $p->delete();
        $this->finishTime();
        $this->get('/admin/history?date=2026-11-09&q='.urlencode('%_!').'&kind=student')->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.person.name', 'Literal %_! student'));
        $this->get('/admin/history?date=2026-11-09&view=attempts')->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 25)->where('records.total', 27));
        $this->get('/admin/history?date=2026-11-09&view=attempts&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 2));
        $this->get('/admin/history?date=2026-11-09&view=attempts&kind=employee&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.current_page', 1)->where('filters.page', 1));
        $this->get('/admin/history?date=2026-11-09&view=attempts&q=NoSuchPerson&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 0)->where('records.current_page', 1)->where('records.total', 0));
    }
}
