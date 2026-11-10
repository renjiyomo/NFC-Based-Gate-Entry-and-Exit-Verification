<?php

namespace Tests;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\Gate;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Detection;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\GateOperations\MissingExits;
use App\Services\Monitoring\PassageCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait AutomaticDirectionWorkflow
{
    abstract protected function directionFixture(): array;

    private function auto(array $f, ?string $id = null): array
    {
        return app(AutomaticPassages::class)->automaticScan($f['guard'], $f['gate'], ['request_id' => $id ?? (string) Str::uuid(), 'payload' => $f['receipt']['payload']], 'direction-session', 'physical');
    }

    public function test_server_alternates_and_same_request_recovers_original_after_policy_changes(): void
    {
        $f = $this->directionFixture();
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $id = (string) Str::uuid();
        $first = $this->auto($f, $id);
        $this->assertSame('entry', $first['passage']['direction']);
        $this->travel(10)->seconds();
        $second = $this->auto($f);
        $this->assertSame('exit', $second['passage']['direction']);
        $this->travel(10)->seconds();
        $this->assertSame('entry', $this->auto($f)['passage']['direction']);
        config(['nfcheck.operating_day_cutoff' => '04:00']);
        $this->assertSame($first['passage']['id'], $this->auto($f, $id)['passage']['id']);
        $this->assertSame($first['passage']['id'], app(AutomaticPassages::class)->recover($f['guard'], $f['gate'], $id, 'direction-session', 'physical')['passage']['id']);
        $this->assertSame(3, AccessEvent::count());
        $this->assertSame(3, ScanAttempt::count());
    }

    public function test_duplicates_are_suppressed_across_gates_and_midnight_before_direction_toggles(): void
    {
        $f = $this->directionFixture();
        $this->travelTo(CarbonImmutable::parse('2026-11-10T15:59:58Z'));
        $first = $this->auto($f);
        $gate = Gate::create(['code' => 'SECOND-'.Str::random(10), 'name' => 'Second synthetic gate', 'status' => 'active']);
        $other = [...$f, 'gate' => $gate, 'guard' => $this->user(['role' => 'guard', 'gate_id' => $gate->id])];
        $this->travel(4)->seconds();
        $duplicate = $this->auto($other);
        $this->assertNull($duplicate['passage']);
        $this->assertSame($first['passage']['id'], $duplicate['duplicate_of']['id']);
        $this->assertSame(1, AccessEvent::count());
        $this->travel(10)->seconds();
        $this->assertSame('entry', $this->auto($other)['passage']['direction']);
        $this->assertSame(1, AnomalyFlag::where('rule_code', 'missing_exit')->count());
    }

    public function test_cutoff_job_flags_missing_exit_without_a_return_scan_and_reconciles_corrections(): void
    {
        $f = $this->directionFixture();
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $this->auto($f);
        $entry = AccessEvent::sole();
        $sweep = app(MissingExits::class);
        $this->assertSame(0, $sweep->sweep('physical')['created']);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T16:00:00Z'));
        $this->assertSame(1, $sweep->sweep('physical')['created']);
        $this->assertSame(0, $sweep->sweep('physical')['created']);
        $this->assertSame(1, AccessEvent::count());
        $flag = AnomalyFlag::where('rule_code', 'missing_exit')->sole();
        $this->assertSame('2026-11-10', $flag->policy['operating_day']);
        $this->assertSame(1, $flag->evidence()->count());
        $this->assertSame('still_supported', app(Detection::class)->context($flag));
        app(PassageCorrections::class)->append($this->admin, $entry, ['action' => 'void', 'expected_revision' => 0, 'reason' => 'Synthetic incorrect Entry correction'], 'physical');
        $this->assertSame('evidence_changed', app(Detection::class)->context($flag));
        $this->assertSame(1, AnomalyFlag::count());
        $this->assertSame('entry', $entry->fresh()->direction);
        $this->assertSame('entry', $this->auto($f)['passage']['direction']);
    }

    public function test_closed_operating_days_do_not_create_missing_exit_flags(): void
    {
        $f = $this->directionFixture();
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $this->auto($f);
        $this->travel(60)->seconds();
        $this->auto($f);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T16:00:00Z'));
        $this->assertSame(0, app(MissingExits::class)->sweep('physical')['created']);
        $this->assertSame('entry', $this->auto($f)['passage']['direction']);
        $this->assertSame(0, AnomalyFlag::count());
    }

    public function test_overnight_cutoff_is_pinned_even_when_current_configuration_changes(): void
    {
        $f = $this->directionFixture();
        config(['nfcheck.operating_day_cutoff' => '04:00']);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T14:00:00Z'));
        $this->auto($f);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T18:00:00Z'));
        config(['nfcheck.operating_day_cutoff' => '00:00']);
        $this->assertSame(0, app(MissingExits::class)->sweep('physical')['created']);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T20:00:00Z'));
        $this->assertSame(1, app(MissingExits::class)->sweep('physical')['created']);
        $this->assertSame('04:00', AnomalyFlag::sole()->policy['cutoff']);
    }

    public function test_http_rejects_client_direction_person_source_and_other_gate(): void
    {
        $f = $this->directionFixture();
        $this->signIn($f['guard']);
        $body = ['payload' => $f['receipt']['payload'], 'request_id' => (string) Str::uuid()];
        $url = '/guard/gates/'.$f['gate']->id.'/automatic-scan';
        foreach (['direction' => 'exit', 'person_id' => $f['person']->id, 'source' => 'simulated'] as $key => $value) {
            $this->postJson($url, [...$body, $key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $other = Gate::create(['code' => 'FORBIDDEN-'.Str::random(8), 'name' => 'Other gate', 'status' => 'active']);
        $this->postJson('/guard/gates/'.$other->id.'/automatic-scan', $body)->assertForbidden();
        $this->postJson($url, $body)->assertOk()->assertJsonPath('passage.direction', 'entry');
        $this->assertSame(1, ScanAttempt::count());
    }

    public function test_revoked_and_invalid_credentials_never_change_state_or_reveal_identity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $f = $this->directionFixture();
        $this->auto($f);
        app(CredentialLifecycle::class)->terminate($this->admin, $f['credential']->fresh(), 'revoke', 'lost');
        $this->travel(20)->seconds();
        $denied = $this->auto($f);
        $this->assertSame('revoked', $denied['verification']['reason']);
        $this->assertNull($denied['identity']);
        $this->assertNull($denied['passage']);
        $invalid = app(AutomaticPassages::class)->automaticScan($f['guard'], $f['gate'], ['request_id' => (string) Str::uuid(), 'payload' => 'invalid'], 'direction-session', 'physical');
        $this->assertSame('denied', $invalid['verification']['outcome']);
        $this->assertNull($invalid['identity']);
        $this->assertSame(1, AccessEvent::count());
    }

    public function test_void_and_reinstate_affect_the_next_direction_without_rewriting_events(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $f = $this->directionFixture();
        $this->auto($f);
        $this->travel(20)->seconds();
        $this->auto($f);
        $exit = AccessEvent::where('direction', 'exit')->sole();
        $correction = app(PassageCorrections::class)->append($this->admin, $exit, ['action' => 'void', 'expected_revision' => 0, 'reason' => 'Synthetic incorrect Exit correction'], 'physical');
        $this->travel(20)->seconds();
        $this->assertSame('exit', $this->auto($f)['passage']['direction']);
        app(PassageCorrections::class)->append($this->admin, $exit, ['action' => 'reinstate', 'expected_revision' => $correction->id, 'reason' => 'Synthetic reinstatement of original Exit'], 'physical');
        $this->travel(20)->seconds();
        $this->assertSame('entry', $this->auto($f)['passage']['direction']);
        $this->assertSame(4, AccessEvent::count());
    }
}
