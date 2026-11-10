<?php

namespace Tests;

use App\Enums\PersonType;
use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\PersonManagement;
use App\Support\PostgresCloud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class PostgresTestCase extends TestCase
{
    protected const PASSWORD = 'orbit river lantern meadow 47';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $target = json_decode(file_get_contents(base_path('.tools/runtime/postgres-test-target.json')), true, 512, JSON_THROW_ON_ERROR);
        $approved = PostgresCloud::load(base_path());
        $checks = ['testing_environment' => app()->environment('testing'), 'owned_run_token' => hash_equals($target['run_token'], (string) getenv('NFCHECK_PG_TEST_RUN')), 'unexpired' => $target['expires_at'] >= time(),
            'approved_host' => $target['connection']['host'] === $approved['host'], 'owned_schema' => preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', $target['schema']) === 1,
            'restricted_role' => preg_match('/^nfcheck_t_[a-f0-9]{16}(\.[a-z0-9]+)?$/D', $target['connection']['username']) === 1];
        if (in_array(false, $checks, true)) {
            throw new \RuntimeException('PostgreSQL test target rejected: '.json_encode($checks, JSON_THROW_ON_ERROR));
        }
        PostgresCloud::configure($target['connection'], $target['schema']);
        config(['app.key' => $target['app_key'], 'nfcheck.nfc_simulator' => false, 'nfcheck.hardware_lab' => false, 'nfcheck.ui_preview' => false]);
        $this->app->forgetInstance('encrypter');
        $identity = DB::selectOne('SELECT current_schema() AS schema, current_user AS actor, current_setting(\'server_version\') AS version');
        if ($identity->schema !== $target['schema'] || $identity->actor !== explode('.', $target['connection']['username'])[0] || $identity->version !== $target['server_version']) {
            throw new \RuntimeException('Unexpected PostgreSQL test identity.');
        }
        DB::beginTransaction();
        $this->admin = $this->user();
        $this->signIn($this->admin);
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && DB::transactionLevel() > 0) {
            DB::rollBack(0);
        }
        if (isset($this->app)) {
            DB::disconnect('pgsql');
        }
        parent::tearDown();
    }

    protected function user(array $attributes = []): User
    {
        return User::create([...['name' => 'Synthetic operator', 'username' => 'test-'.Str::random(12), 'email' => Str::uuid().'@example.invalid', 'password' => self::PASSWORD, 'role' => 'admin'], ...$attributes]);
    }

    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version, 'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->password)]);
    }

    protected function fixture(): array
    {
        $person = app(PersonManagement::class)->save($this->admin, PersonType::Student, ['campus_number' => 'PG-'.Str::random(10), 'full_name' => 'Synthetic PostgreSQL Student', 'status' => 'active', 'program' => 'BSCS', 'year_level' => 2, 'academic_year' => '2026-2027', 'semester' => '1st Semester']);
        $service = app(CredentialLifecycle::class);
        $receipt = $service->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->record_version, 'label' => 'PG-'.Str::random(12), 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'issuer', 'physical');
        $credential = NfcCredential::where('public_id', $receipt['credential']['id'])->sole();
        $service->readBack($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $service->activate($this->admin, $credential, 'issuer', $receipt['payload'], $receipt['completion_nonce'], 'physical');
        $gate = Gate::create(['code' => 'PG-'.Str::random(12), 'name' => 'Synthetic PostgreSQL Gate', 'status' => 'active']);
        $guard = $this->user(['role' => 'guard', 'gate_id' => $gate->id]);

        return compact('person', 'credential', 'gate', 'guard', 'receipt');
    }
}
