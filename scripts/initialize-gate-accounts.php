<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GateAccounts;
use App\Support\IsolatedDatabase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$workspace = $argv[1] ?? '';
$env = match ($workspace) {
    'main' => '.env',
    'simulator' => '.env.simulator',
    'hardware' => '.env.hardware',
    default => null,
};
if (! $env) {
    exit(1);
}
foreach (Dotenv\Dotenv::parse(file_get_contents($root.'/'.$env)) as $k => $v) {
    putenv($k.'='.$v);
    $_ENV[$k] = $_SERVER[$k] = $v;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedDatabase::assertMigrationTarget();
if (config('database.connections.mysql.database') !== match ($workspace) {
    'main' => 'nfcheck',
    'simulator' => 'nfcheck_simulator',
    'hardware' => 'nfcheck_hardware',
}) {
    throw new RuntimeException('Workspace mismatch.');
}
$handoff = $root.'/.tools/secrets/operator-accounts.json';
$all = is_file($handoff) ? json_decode(file_get_contents($handoff), true, 512, JSON_THROW_ON_ERROR) : [];
$known = [];
$collect = function (array $a) use (&$collect, &$known) {
    if (isset($a['email'], $a['password']) && is_string($a['password'])) {
        $known[$a['email']][] = $a['password'];
    }
    foreach ($a as $v) {
        if (is_array($v)) {
            $collect($v);
        }
    }
};
foreach (glob($root.'/.tools/secrets/*.json') as $file) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) {
        $collect($d);
    }
}
$admin = User::where('role', 'admin')->where('status', 'active')->firstOrFail();
$accounts = [];
foreach (Gate::where('status', 'active')->get() as $gate) {
    $account = $gate->account;
    $password = null;
    if (! $account) {
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $username = strtolower($gate->code);
        $account = app(GateAccounts::class)->save($admin, $gate, ['username' => $username, 'password' => $password]);
    } else {
        $password = $all[$workspace]['gates'][$account->username]['password'] ?? null;
        if (! is_string($password) || ! Hash::check($password, $account->password)) {
            $password = null;
        }
    }
    $accounts[$account->username] = ['username' => $account->username, 'gate' => $gate->name, 'password' => $password, 'status' => $account->status->value, 'password_note' => $password ? 'Private handoff password verified against stored hash.' : 'Password changed or unavailable; unchanged. An administrator can reset it on the gate form.'];
}
// Old individual guard logins are retained as inactive historical actors.
foreach (User::where('role', 'guard')->whereNull('gate_id')->where('status', 'active')->get() as $legacy) {
    $legacy->status = 'inactive';
    $legacy->save();
    app(AuditLogger::class)->record('account.legacy_guard_retired', $admin, $legacy, ['status' => 'inactive']);
}
$admins = [];
foreach (User::where('role', 'admin')->where('status', 'active')->get() as $a) {
    $password = null;
    foreach ($known[$a->email] ?? [] as $candidate) {
        if (Hash::check($candidate, $a->password)) {
            $password = $candidate;
            break;
        }
    }
    $admins[] = ['username' => $a->username, 'name' => $a->name, 'password' => $password, 'password_note' => $password ? 'Existing initial password verified against stored hash.' : 'Personal password; unchanged and not recoverable.'];
}
$all[$workspace] = ['admins' => $admins, 'gates' => $accounts];
file_put_contents($handoff, json_encode($all, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
echo json_encode(['workspace' => $workspace, 'admins' => array_column($admins, 'username'), 'gates' => array_keys($accounts), 'private_handoff' => 'operator-accounts.json'], JSON_THROW_ON_ERROR).PHP_EOL;
