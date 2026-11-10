<?php

namespace Tests;

use App\Models\Gate;
use App\Models\NfcCredential;
use App\Models\User;
use App\Services\Credentials\CredentialLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;

final class PostgresRaces
{
    public static function run(string $root, array $target, PDO $monitor): array
    {
        $results = [];
        foreach (['different_request_ids', 'same_request_id', 'revoke_before_scan', 'scan_before_revoke', 'different_gates', 'issuance_labels'] as $case) {
            $fixture = PostgresFixtures::create();
            $secondFixture = $fixture;
            if ($case === 'issuance_labels') {
                $secondFixture = PostgresFixtures::create();
                foreach ([$fixture, $secondFixture] as $f) {
                    app(CredentialLifecycle::class)->terminate(User::findOrFail($f['admin_id']), NfcCredential::findOrFail($f['credential_id']), 'revoke', 'damaged');
                }
            }
            if ($case === 'different_gates') {
                $secondGate = Gate::create(['code' => 'PG-RACE-'.Str::random(10), 'name' => 'Synthetic second gate', 'status' => 'active']);
                $secondGuard = User::create(['name' => 'Synthetic second guard', 'username' => 'race-'.Str::random(12), 'email' => Str::uuid().'@example.invalid', 'password' => 'synthetic race password 47', 'role' => 'guard', 'gate_id' => $secondGate->id]);
                $secondFixture = [...$fixture, 'guard_id' => $secondGuard->id, 'gate_id' => $secondGate->id];
            }
            $firstRequest = (string) Str::uuid();
            $prefix = $root.'/.tools/runtime/pg-race-'.bin2hex(random_bytes(8));
            $held = $prefix.'-held';
            $release = $prefix.'-release';
            $startedA = $prefix.'-started-a';
            $startedB = $prefix.'-started-b';
            $env = ['APP_ENV' => 'testing', 'ARGON_MEMORY' => '1024', 'ARGON_TIME' => '1', 'NFCHECK_PG_TEST_RUN' => $target['run_token']];
            $base = ['fixture' => $fixture, 'held' => $held, 'release' => $release, 'clock' => now()->toISOString()];
            $a = new Process([PHP_BINARY, $root.'/scripts/postgres-race-worker.php'], $root, $env);
            $a->setInput(json_encode([...$base, 'started' => $startedA, 'hold' => true, 'kind' => $case === 'issuance_labels' ? 'issue' : ($case === 'revoke_before_scan' ? 'revoke' : 'scan'), 'request_id' => $firstRequest], JSON_THROW_ON_ERROR));
            $a->setTimeout(45);
            $b = new Process([PHP_BINARY, $root.'/scripts/postgres-race-worker.php'], $root, $env);
            $b->setInput(json_encode([...$base, 'fixture' => $secondFixture, 'started' => $startedB, 'hold' => false, 'kind' => $case === 'issuance_labels' ? 'issue' : ($case === 'scan_before_revoke' ? 'revoke' : 'scan'), 'request_id' => $case === 'same_request_id' ? $firstRequest : (string) Str::uuid()], JSON_THROW_ON_ERROR));
            $b->setTimeout(45);
            try {
                $a->start();
                $deadline = microtime(true) + 10;
                while (! is_file($held) && $a->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                if (! is_file($held)) {
                    throw new \RuntimeException('The first PostgreSQL race worker did not acquire its anchors.');
                }
                $b->start();
                $overlap = false;
                $wait = $monitor->prepare("SELECT count(*) FROM pg_stat_activity WHERE pid = ? AND wait_event_type = 'Lock'");
                $deadline = microtime(true) + 15;
                while (microtime(true) < $deadline && $b->isRunning()) {
                    if (! is_file($startedB)) {
                        usleep(20000);

                        continue;
                    }
                    $wait->execute([(int) file_get_contents($startedB)]);
                    if ((int) $wait->fetchColumn() > 0) {
                        $overlap = true;
                        break;
                    }
                    usleep(20000);
                }
                file_put_contents($release, 'release');
                $a->wait();
                $b->wait();
                if (! $a->isSuccessful() || ! $b->isSuccessful() || ! $overlap) {
                    throw new \RuntimeException('PostgreSQL race worker failure or unobserved overlap.');
                }
                $events = DB::table('access_events')->where('person_id', $fixture['person_id'])->count();
                $attempts = DB::table('scan_attempts')->where('person_id', $fixture['person_id'])->count();
                $expectedEvents = in_array($case, ['revoke_before_scan', 'issuance_labels'], true) ? 0 : 1;
                $expectedAttempts = $case === 'issuance_labels' ? 0 : (in_array($case, ['same_request_id', 'scan_before_revoke', 'revoke_before_scan'], true) ? 1 : 2);
                if ($case === 'issuance_labels') {
                    $outA = json_decode($a->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                    $outB = json_decode($b->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                    if ($outA['label'] === $outB['label']) {
                        throw new \RuntimeException('Concurrent labels collide.');
                    }
                }
                if ($events !== $expectedEvents || $attempts !== $expectedAttempts) {
                    throw new \RuntimeException('PostgreSQL race records did not reconcile.');
                }
                $results[$case] = ['passed' => true, 'overlap_observed' => true, 'events' => $events, 'attempts' => $attempts];
            } finally {
                file_put_contents($release, 'release');
                if ($a->isRunning()) {
                    $a->wait();
                }
                if ($b->isRunning()) {
                    $b->wait();
                }
                foreach ([$held, $release, $startedA, $startedB] as $ownedFile) {
                    if (is_file($ownedFile)) {
                        unlink($ownedFile);
                    }
                }
            }
        }

        return $results;
    }
}
