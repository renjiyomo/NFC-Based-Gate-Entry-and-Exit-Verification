<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\AnomalyFlag;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\ScanAttempt;
use App\Services\Anomalies\Detection;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\Monitoring\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\ManagementTestCase;

class SingleGateActivityTest extends ManagementTestCase
{
    private function fixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        config(['anomalies.threshold_rules' => true, 'anomalies.rapid_count' => 3, 'anomalies.cross_gate_enabled' => false]);
        $person = $this->makePerson();
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'activity-issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'activity-issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($this->admin, $credential, 'activity-issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $gate = Gate::create(['code' => 'ACT-'.Str::random(10), 'name' => 'Synthetic single gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard', 'gate_id' => $gate->id]);

        return compact('person', 'credential', 'receipt', 'gate', 'guard');
    }

    private function scan(array $fixture, ?string $request = null): array
    {
        return app(AutomaticPassages::class)->automaticScan($fixture['guard'], $fixture['gate'], ['request_id' => $request ?? (string) Str::uuid(), 'payload' => $fixture['receipt']['payload']], 'activity-session', 'physical');
    }

    public function test_three_distinct_scans_create_one_flag_automatically_and_retry_does_not_repeat_it(): void
    {
        $f = $this->fixture();
        $this->scan($f);
        $this->travel(11)->seconds();
        $this->scan($f);
        $this->assertSame(0, AnomalyFlag::count());
        $this->travel(11)->seconds();
        $this->scan($f);
        $flag = AnomalyFlag::where('rule_code', 'rapid_scans')->sole();
        $this->assertSame(3, $flag->evidence()->count());
        $this->assertSame(3, AccessEvent::count());
        $this->assertSame('still_supported', app(Detection::class)->context($flag));
        $this->travel(11)->seconds();
        $request = (string) Str::uuid();
        $this->scan($f, $request);
        $this->scan($f, $request);
        $this->assertSame(1, AnomalyFlag::count());
        $this->assertSame(4, ScanAttempt::count());
        $this->assertSame(4, AccessEvent::count());
        $this->assertSame(0, app(Detection::class)->run(new ReportFilters(['date' => '2026-11-10']), 'physical', $this->admin)['created']);
    }

    public function test_the_sixty_second_lower_boundary_is_excluded(): void
    {
        $f = $this->fixture();
        $this->scan($f);
        $this->travel(30)->seconds();
        $this->scan($f);
        $this->travel(30)->seconds();
        $this->scan($f);
        $this->assertSame(0, AnomalyFlag::count());
        $this->travel(1)->seconds();
        $repeat = $this->scan($f);
        $this->assertNotNull($repeat['duplicate_of']);
        $this->assertSame(1, AnomalyFlag::where('rule_code', 'rapid_scans')->count());
        $this->assertSame(3, AccessEvent::count());
    }

    public function test_first_revoked_coin_use_is_flagged_and_repeat_reads_are_grouped(): void
    {
        $f = $this->fixture();
        $f['credential']->forceFill(['status' => 'revoked'])->save();
        $this->scan($f);
        $this->travel(2)->seconds();
        $this->scan($f);
        $flag = AnomalyFlag::where('rule_code', 'revoked_use')->sole();
        $this->assertSame(1, $flag->evidence()->count());
        $this->assertSame(0, AccessEvent::count());
        $this->assertSame(2, ScanAttempt::where('outcome', 'denied')->count());
    }

    public function test_inactive_person_is_flagged_without_recording_a_passage(): void
    {
        $f = $this->fixture();
        $f['person']->forceFill(['status' => 'inactive'])->save();
        $this->scan($f);
        $this->assertSame('unavailable_credential', AnomalyFlag::sole()->rule_code);
        $this->assertSame(0, AccessEvent::count());
    }

    public function test_manual_date_check_also_finds_missing_exit_at_its_cutoff_date(): void
    {
        $f = $this->fixture();
        $this->scan($f);
        $this->travelTo(CarbonImmutable::parse('2026-11-10T16:01:00Z'));
        $result = app(Detection::class)->run(new ReportFilters(['date' => '2026-11-11']), 'physical', $this->admin);
        $this->assertSame(1, $result['created']);
        $flag = AnomalyFlag::where('rule_code', 'missing_exit')->sole();
        $this->assertSame('2026-11-11', $flag->occurred_at->setTimezone('Asia/Manila')->toDateString());
        $this->assertSame(1, AccessEvent::count());
        $this->assertSame(0, app(Detection::class)->run(new ReportFilters(['date' => '2026-11-11']), 'physical', $this->admin)['created']);
    }

    public function test_paused_threshold_checks_do_not_change_successful_passages(): void
    {
        $f = $this->fixture();
        config(['anomalies.threshold_rules' => false]);
        foreach ([0, 11, 22] as $seconds) {
            $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z')->addSeconds($seconds));
            $this->scan($f);
        }
        $this->assertSame(3, AccessEvent::count());
        $this->assertSame(0, AnomalyFlag::count());
    }
}
