<?php

namespace Tests\Feature;

use App\Models\ScanAttempt;
use App\Services\GateOperations\GateOperations;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\MonitoringTestCase;

class CurrentPresenceTest extends MonitoringTestCase
{
    public function test_counts_people_once_by_latest_effective_direction_and_person_type(): void
    {
        $one = $this->subject();
        $two = $this->subject();
        $employee = $this->subject('employees');
        $outside = $this->subject('employees');
        $pending = $this->subject();
        $simulated = $this->subject('employees', 'simulated');
        $future = $this->subject();
        $this->passage($one, '2026-11-08 01:00:00');
        $this->passage($one, '2026-11-09 01:00:00');
        $this->passage($two, '2026-11-09 01:30:00');
        $this->passage($employee, '2026-11-09 02:00:00');
        $this->passage($outside, '2026-11-09 02:30:00');
        $this->passage($outside, '2026-11-09 03:00:00', 'exit');
        $repeat = $this->scanPerson($employee, '2026-11-09 02:00:05');
        app(GateOperations::class)->review($this->guard, $this->gate, ScanAttempt::where('public_id', $repeat['id'])->sole(), ['action' => 'confirm', 'identity_checked' => true, 'identity_method' => 'campus_document'], 'report-guard', 'physical');
        $this->scanPerson($pending, '2026-11-09 03:30:00');
        config(['nfcheck.nfc_simulator' => true]);
        $this->passage($simulated, '2026-11-09 04:00:00', 'entry', 'simulated');
        config(['nfcheck.nfc_simulator' => false]);
        $this->passage($future, '2026-11-11 01:00:00');
        $one->forceFill(['status' => 'inactive'])->save();
        $one->delete();
        $this->finishTime();
        $q = app(ReportQueries::class);
        $presence = $q->presence(new ReportFilters, 'physical');
        $this->assertSame([3, 2, 1, 1], [$presence['inside'], $presence['students_inside'], $presence['employees_inside'], $presence['outside']]);
        $this->assertSame($presence['inside'], $presence['students_inside'] + $presence['employees_inside']);
        $this->assertSame(1, $q->presence(new ReportFilters, 'simulated')['employees_inside']);
    }

    public function test_void_and_reinstatement_recalculate_the_correct_category(): void
    {
        $student = $this->subject();
        $employee = $this->subject('employees');
        $this->passage($student, '2026-11-08 01:00:00');
        $this->passage($employee, '2026-11-08 02:00:00');
        $exit = $this->passage($student, '2026-11-09 01:00:00', 'exit');
        $this->finishTime();
        $q = app(ReportQueries::class);
        $this->assertSame(0, $q->presence(new ReportFilters, 'physical')['students_inside']);
        $corrections = app(PassageCorrections::class);
        $void = $corrections->append($this->admin, $exit, ['action' => 'void', 'reason' => 'Synthetic inaccurate Exit correction', 'expected_revision' => 0], 'physical');
        $presence = $q->presence(new ReportFilters, 'physical');
        $this->assertSame([2, 1, 1], [$presence['inside'], $presence['students_inside'], $presence['employees_inside']]);
        $corrections->append($this->admin, $exit, ['action' => 'reinstate', 'reason' => 'Synthetic reviewed Exit reinstatement', 'expected_revision' => $void->id], 'physical');
        $presence = $q->presence(new ReportFilters, 'physical');
        $this->assertSame([1, 0, 1], [$presence['inside'], $presence['students_inside'], $presence['employees_inside']]);
    }

    public function test_dashboard_current_counts_ignore_historical_activity_filters_and_refresh_together(): void
    {
        $student = $this->subject();
        $employee = $this->subject('employees');
        $this->passage($student, '2026-11-09 01:00:00');
        $this->passage($employee, '2026-11-09 02:00:00');
        $this->finishTime();
        $url = '/admin/dashboard?date=2026-11-01&kind=employee&q=NoMatchingPerson&direction=exit&gate_id='.$this->gate->id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.entries', 0)->where('summary.presence.inside', 0)
            ->where('currentPresence.inside', 2)->where('currentPresence.students_inside', 1)
            ->where('currentPresence.employees_inside', 1)->where('currentPresence.as_of', now()->toIso8601String()));
        $this->getJson($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(), 'X-Inertia-Partial-Component' => 'monitoring/Index', 'X-Inertia-Partial-Data' => 'currentPresence'])
            ->assertOk()->assertJsonPath('props.currentPresence.inside', 2)
            ->assertJsonPath('props.currentPresence.students_inside', 1)->assertJsonPath('props.currentPresence.employees_inside', 1);
    }
}
