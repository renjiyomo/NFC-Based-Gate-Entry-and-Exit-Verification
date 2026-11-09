<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\ManagementTestCase;

class GateAndGuardManagementTest extends ManagementTestCase
{
    private function details(array $extra = []): array
    {
        return [...['name' => 'Synthetic gate', 'code' => 'GATE-NEW', 'status' => 'active', 'username' => 'gate.new', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD], ...$extra];
    }

    public function test_gate_login_is_unique_and_a_conflicting_username_rolls_back_the_gate(): void
    {
        Mail::fake();
        $this->post('/admin/gates', $this->details())->assertSessionHasNoErrors();
        $this->post('/admin/gates', $this->details(['code' => 'GATE-OTHER']))->assertSessionHasErrors('username');
        $this->assertSame(1, Gate::count());
        $this->assertSame(1, User::whereNotNull('gate_id')->count());
        Mail::assertNothingSent();
    }

    public function test_gate_edits_preserve_password_unless_reset_and_reject_stale_versions(): void
    {
        $this->post('/admin/gates', $this->details());
        $gate = Gate::sole();
        $hash = $gate->account->password;
        $this->put('/admin/gates/'.$gate->id, $this->details(['name' => 'Renamed gate', 'password' => '', 'password_confirmation' => '', 'record_version' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($hash, $gate->account->fresh()->password);
        $this->put('/admin/gates/'.$gate->id, $this->details(['record_version' => 1]))->assertSessionHasErrors('record_version');
        $this->assertSame('Renamed gate', $gate->fresh()->name);
        $password = 'new orbit maple station river 94';
        $before = $gate->account->fresh()->auth_version;
        $this->put('/admin/gates/'.$gate->id, $this->details(['password' => $password, 'password_confirmation' => $password, 'record_version' => 2]))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $gate->account->fresh()->password));
        $this->assertGreaterThan($before, $gate->account->fresh()->auth_version);
    }

    public function test_archive_and_restore_keep_gate_and_login_history_but_require_activation(): void
    {
        $this->post('/admin/gates', $this->details());
        $gate = Gate::sole();
        $accountId = $gate->account->id;
        $this->delete('/admin/gates/'.$gate->id, ['record_version' => 1])->assertRedirect();
        $this->assertNotNull(User::find($accountId));
        $this->post('/admin/gates/'.$gate->id.'/restore', ['record_version' => 2])->assertRedirect();
        $this->assertSame('inactive', $gate->fresh()->status->value);
        $this->assertSame($accountId, $gate->fresh()->account->id);
    }

    public function test_guards_cannot_create_or_change_other_gate_accounts(): void
    {
        $this->post('/admin/gates', $this->details());
        $gate = Gate::sole();
        $this->signIn($gate->account);
        $this->get('/admin/gates')->assertForbidden();
        $this->post('/admin/gates', $this->details())->assertForbidden();
        $this->put('/admin/gates/'.$gate->id, $this->details(['record_version' => 1]))->assertForbidden();
    }

    public function test_individual_guard_creation_and_email_setup_are_no_longer_available(): void
    {
        $this->get('/admin/guards/create')->assertRedirect('/admin/gates/create');
        $this->post('/admin/guards', ['name' => 'Old operator', 'email' => 'old@example.invalid'])->assertStatus(410);
        $this->assertSame(0, User::where('role','guard')->count());
    }
}
