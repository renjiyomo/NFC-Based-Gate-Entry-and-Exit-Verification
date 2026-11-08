<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class DatabaseTestCase extends TestCase
{
    protected const PASSWORD = 'orbit river lantern meadow 47';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $connection = config('database.connections.mysql');
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] !== 3307
            || $connection['database'] !== 'nfcheck_testing' || $connection['username'] !== 'nfcheck_test') {
            throw new \RuntimeException('Refusing database tests outside the isolated testing schema.');
        }
        $identity = DB::selectOne('SELECT DATABASE() AS db, VERSION() AS version');
        if ($identity->db !== 'nfcheck_testing' || $identity->version !== '8.4.13') {
            throw new \RuntimeException('Unexpected test database identity.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && DB::transactionLevel() > 0) {
            DB::rollBack(0);
        }
        parent::tearDown();
    }

    protected function user(array $attributes = []): User
    {
        return User::create([...['name' => 'Test operator', 'email' => Str::uuid().'@example.invalid', 'password' => self::PASSWORD, 'role' => 'admin'], ...$attributes]);
    }

    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version, 'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->password)]);
    }
}
