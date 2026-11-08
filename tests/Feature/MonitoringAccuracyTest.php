<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\ScanAttempt;
use App\Services\GateOperations\GateOperations;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use App\Services\Monitoring\ReportTable;
use Carbon\CarbonImmutable;
use Tests\MonitoringTestCase;

class MonitoringAccuracyTest extends MonitoringTestCase
{
    public function test_snapshot_keeps_microseconds_and_presence_uses_earlier_effective_directions(): void
    {
        $p = $this->subject();
        $unknown = $this->subject('employees');
        $event = $this->passage($p, '2026-11-09 01:00:00.500000');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 01:00:00.750000', 'UTC'));
        $f = new ReportFilters(['date' => '2026-11-09']);
        $query = app(ReportQueries::class);
        $this->assertSame(1, $query->summary($f, 'physical')['entries']);
        $before = new ReportFilters(['date' => '2026-11-09'], CarbonImmutable::parse('2026-11-09 01:00:00.250000', 'UTC'));
        $this->assertSame(0, $query->summary($before, 'physical')['entries']);
        $this->finishTime();
        $later = new ReportFilters(['date' => '2026-11-10']);
        $s = $query->summary($later, 'physical');
        $this->assertSame(0, $s['passages']);
        $this->assertSame(1, $s['presence']['inside']);
        $this->assertSame(1, $s['presence']['unknown']);
        app(PassageCorrections::class)->append($this->admin, $event, ['action' => 'void', 'reason' => 'Synthetic correction removes prior Entry', 'expected_revision' => 0], 'physical');
        $s = $query->summary($later, 'physical');
        $this->assertSame(0, $s['presence']['inside']);
        $this->assertSame(2, $s['presence']['unknown']);
    }

    public function test_current_month_does_not_draw_future_days_as_observed_zeroes(): void
    {
        $this->finishTime();
        $q = app(ReportQueries::class);
        $month = $q->summary(new ReportFilters(['period' => 'monthly', 'date' => '2026-11-10']), 'physical');
        $this->assertCount(10, $month['trend']);
        $this->assertSame('2026-11-10', $month['trend'][9]['date']);
        $future = $q->summary(new ReportFilters(['date' => '2026-12-01']), 'physical');
        $this->assertSame([], $future['trend']);
    }

    public function test_voided_passages_do_not_suppress_new_passage_or_leak_stale_guard_state(): void
    {
        $p = $this->subject();
        $event = $this->passage($p, '2026-11-09 01:00:00');
        $this->travelTo(now()->addSecond());
        app(PassageCorrections::class)->append($this->admin, $event, ['action' => 'void', 'reason' => 'Synthetic erroneous original Entry', 'expected_revision' => 0], 'physical');
        $saved = app(GateOperations::class)->recover($this->guard, $this->gate, $event->attempt->request_id, 'report-guard', 'physical');
        $this->assertTrue($saved['passage']['voided']);
        $second = $this->passage($p, '2026-11-09 01:00:05');
        $this->assertNotSame($event->id, $second->id);
        $this->assertSame(2, AccessEvent::count());
        $this->assertSame(1, AccessEvent::effective()->count());
    }

    public function test_simulator_reporting_is_source_fixed_and_live_history_has_all_advanced_filters(): void
    {
        $physical = $this->subject();
        $sim = $this->subject('employees', 'simulated');
        $this->passage($physical, '2026-11-09 01:00:00');
        config(['nfcheck.nfc_simulator' => true]);
        $event = $this->passage($sim, '2026-11-09 02:00:00', 'entry', 'simulated');
        $this->finishTime();
        $this->get('/admin/dashboard?date=2026-11-09')->assertOk()->assertInertia(fn ($p) => $p->where('source', 'simulated')->where('summary.entries', 1)->where('directory.active_credentials', 1));
        config(['nfcheck.nfc_simulator' => false]);
        $url = '/admin/history?date=2026-11-09&view=attempts&gate_id='.$this->gate->id.'&guard_id='.$this->guard->id.'&direction=entry&kind=student&outcome=allowed&review=confirmed';
        $this->get($url)->assertOk()->assertInertia(fn ($p) => $p->has('records.data', 1));
        $this->get($url.'&q=never-match')->assertOk()->assertInertia(fn ($p) => $p->has('records.data', 0));
    }

    public function test_cancelled_and_expired_reviews_are_not_passages_and_filter_as_effective_state(): void
    {
        $p = $this->subject();
        $scan = $this->scanPerson($p, '2026-11-09 01:00:00');
        app(GateOperations::class)->review($this->guard, $this->gate, ScanAttempt::where('public_id', $scan['id'])->sole(), ['action' => 'cancel'], 'report-guard', 'physical');
        $this->scanPerson($p, '2026-11-09 02:00:00');
        $this->finishTime();
        $this->get('/admin/history?date=2026-11-09&view=attempts&review=expired')->assertOk()->assertInertia(fn ($p) => $p->has('records.data', 1)->where('records.data.0.review', 'expired'));
        $s = app(ReportQueries::class)->summary(new ReportFilters(['date' => '2026-11-09']), 'physical');
        $this->assertSame(0, $s['passages']);
        $this->assertSame(2, $s['attempts']);
        $table = app(ReportTable::class)->build(new ReportFilters(['date' => '2026-11-09', 'review' => 'cancelled']), 'physical', 'attempts', 100);
        $this->assertCount(1, $table['rows']);
        $this->assertSame('cancelled', $table['rows'][0][9]);
    }
}
