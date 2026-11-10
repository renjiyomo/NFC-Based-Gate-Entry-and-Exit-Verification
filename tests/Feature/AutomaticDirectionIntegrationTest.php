<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Services\Credentials\CredentialLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\AutomaticDirectionWorkflow;
use Tests\ManagementTestCase;

class AutomaticDirectionIntegrationTest extends ManagementTestCase
{
    use AutomaticDirectionWorkflow;

    protected function directionFixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));
        $person = $this->makePerson();
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'STATE-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $gate = Gate::create(['code' => 'STATE-'.Str::random(10), 'name' => 'Synthetic state gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard', 'gate_id' => $gate->id]);

        return compact('person', 'credential', 'receipt', 'gate', 'guard');
    }

    public function test_cron_rejects_missing_or_incorrect_secret(): void
    {
        config(['nfcheck.cron_secret' => null]);
        $this->getJson('/internal/missing-exits')->assertStatus(503);
        config(['nfcheck.cron_secret' => str_repeat('x', 32)]);
        $this->getJson('/internal/missing-exits')->assertForbidden();
        $this->withToken('incorrect')->getJson('/internal/missing-exits')->assertForbidden();
        $this->withToken(str_repeat('x', 32))->getJson('/internal/missing-exits')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_manual_http_scans_are_retired_and_scanner_props_do_not_select_direction(): void
    {
        $f = $this->directionFixture();
        $this->signIn($f['guard']);
        $this->postJson('/guard/gates/'.$f['gate']->id.'/scan', ['request_id' => (string) Str::uuid(), 'payload' => $f['receipt']['payload'], 'direction' => 'exit'])->assertStatus(410);
        $this->get('/guard/gates/'.$f['gate']->id.'/scanner')->assertInertia(fn (AssertableInertia $p) => $p->component('operations/Scanner')->missing('requestDirection')->missing('reviewSeconds'));
        $this->assertSame(0, AccessEvent::count());
    }
}
