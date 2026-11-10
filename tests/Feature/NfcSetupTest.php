<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureHardwareWorkspace;
use App\Http\Middleware\EnsureNfcSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\DatabaseTestCase;

class NfcSetupTest extends DatabaseTestCase
{
    public function test_setup_requires_authentication_and_completed_password_setup(): void
    {
        $this->get('/nfc/setup')->assertRedirect('/login');
        $this->signIn($this->user(['must_change_password' => true]))->get('/nfc/setup')->assertRedirect('/account/password');
    }

    public function test_admin_and_guard_can_open_diagnostics_without_creating_nfc_records(): void
    {
        $counts = array_map(fn ($table) => DB::table($table)->count(), ['nfc_credentials', 'scan_attempts', 'access_events']);
        foreach (['admin', 'guard'] as $role) {
            $this->signIn($this->user(['role' => $role]))->get('/nfc/setup')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('nfc/Setup')->where('app.hardwareLab', false)->missing('payload')->missing('completion_nonce'));
        }
        $this->assertSame($counts, array_map(fn ($table) => DB::table($table)->count(), ['nfc_credentials', 'scan_attempts', 'access_events']));
    }

    public function test_hardware_workspace_requires_explicit_flag_loopback_and_nonproduction(): void
    {
        $request = Request::create('/login', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $database = config('database.connections.mysql.database');
        try {
            config(['database.connections.mysql.database' => 'nfcheck_hardware', 'nfcheck.hardware_lab' => true, 'nfcheck.nfc_simulator' => true]);
            $this->assertTrue(EnsureHardwareWorkspace::available($request));
            $this->assertFalse(EnsureNfcSimulator::available($request));
            $this->assertFalse(EnsureHardwareWorkspace::available(Request::create('/login', 'GET', server: ['REMOTE_ADDR' => '192.168.1.50'])));
            config(['nfcheck.hardware_lab' => false]);
            $this->assertFalse(EnsureHardwareWorkspace::available($request));
            $this->get('/login')->assertNotFound();
            config(['nfcheck.hardware_lab' => true]);
            app()->instance('env', 'production');
            $this->assertFalse(EnsureHardwareWorkspace::available($request));
            $this->get('/login')->assertNotFound();
        } finally {
            config(['database.connections.mysql.database' => $database, 'nfcheck.hardware_lab' => false, 'nfcheck.nfc_simulator' => false]);
            app()->instance('env', 'testing');
        }
    }
}
