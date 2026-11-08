<?php

namespace Tests\Feature;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class SessionStorageTest extends DatabaseTestCase
{
    public function test_database_session_payload_is_encrypted_and_invalidation_removes_it(): void
    {
        $manager = app('session');
        $store = $manager->driver('database');
        $this->assertInstanceOf(EncryptedStore::class, $store);
        $store->start();
        $store->put('sensitive_test_marker', 'private-value-that-must-be-encrypted');
        $store->save();
        $id = $store->getId();
        $payload = DB::table('sessions')->where('id', $id)->value('payload');
        $this->assertNotNull($payload);
        $this->assertStringNotContainsString('private-value-that-must-be-encrypted', base64_decode($payload));
        $this->assertStringContainsString('private-value-that-must-be-encrypted', app('encrypter')->decrypt(base64_decode($payload), false));
        $store->invalidate();
        $this->assertDatabaseMissing('sessions', ['id' => $id]);
        $this->assertNotSame($id, $store->getId());
    }

    public function test_idle_sessions_expire_after_the_configured_thirty_minutes(): void
    {
        $handler = new DatabaseSessionHandler(DB::connection(), 'sessions', config('session.lifetime'));
        DB::table('sessions')->insert(['id' => 'expired-test', 'payload' => base64_encode('opaque-expired-payload'), 'last_activity' => now()->subMinutes(31)->timestamp]);
        DB::table('sessions')->insert(['id' => 'current-test', 'payload' => base64_encode('opaque-current-payload'), 'last_activity' => now()->subMinutes(29)->timestamp]);
        $this->assertSame('', $handler->read('expired-test'));
        $this->assertSame('opaque-current-payload', $handler->read('current-test'));
    }
}
