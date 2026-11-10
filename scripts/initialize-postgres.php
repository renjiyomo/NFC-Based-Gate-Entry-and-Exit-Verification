<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AccountService;
use App\Services\GateManagement;
use App\Support\CloudMigrationTarget;
use App\Support\PostgresCloud;
use App\Support\PostgresSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$runtimePath = $root.'/.tools/secrets/cloud-runtime.json';
$accountsPath = $root.'/.tools/secrets/cloud-operator-accounts.json';
$createdFiles = [];
$commitStarted = false;
$result = ['initialized' => false];
try {
    $c = PostgresCloud::load($root);
    $receipt = json_decode(file_get_contents($root.'/.tools/artifacts/postgres-validation.json'), true, 512, JSON_THROW_ON_ERROR);
    $fingerprint = hash('sha256', $c['host']."\0".$c['database']."\0".$c['username']."\0".hash_file('sha256', $c['ca_file']));
    if (! ($receipt['passed'] ?? false) || ! ($receipt['owned_schema_removed'] ?? false) || ! ($receipt['owned_role_removed'] ?? false)
        || ! hash_equals($fingerprint, $receipt['recovery']['connection_fingerprint'] ?? '')) {
        throw new RuntimeException('A successful disposable validation/restore receipt for this exact target is required.');
    }
    if (is_file($runtimePath) || is_file($accountsPath)) {
        throw new RuntimeException('A private online handoff already exists. It will not be overwritten or rotated.');
    }
    $admin = PostgresCloud::connect($c);
    $existing = $admin->prepare('SELECT 1 FROM pg_namespace WHERE nspname = ?');
    $existing->execute(['nfcheck_online']);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('The intended online schema already exists. No reset or replacement is allowed.');
    }
    PostgresCloud::configure($c, 'nfcheck_online');
    $connection = DB::connection();
    $connection->beginTransaction();
    // One transaction owns schema, roles, catalogues and account initialization.
    $pdo = $connection->getPdo();
    PostgresSchema::create($pdo, 'nfcheck_online');
    // configure() would purge this transaction: use the already selected connection.
    CloudMigrationTarget::authorize($root, 'nfcheck_online');
    try {
        if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Online migrations failed.');
        }
    } finally {
        CloudMigrationTarget::clear();
    }
    PostgresSchema::harden($pdo, 'nfcheck_online');
    $role = 'nfcheck_app_'.bin2hex(random_bytes(4));
    $password = bin2hex(random_bytes(24));
    $pdo->exec('CREATE ROLE '.PostgresSchema::identifier($role).' LOGIN NOINHERIT PASSWORD '.$pdo->quote($password));
    PostgresSchema::grantRuntime($pdo, 'nfcheck_online', $role);
    $accounts = [];
    $firstAdmin = null;
    $settings = json_decode(ltrim(file_get_contents($root.'/.tools/secrets/cloud-services.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
    $administrators = $settings['administrators'] ?? [];
    if (count($administrators) < 1 || count($administrators) > 10) {
        throw new RuntimeException('Configure the intended online administrators privately.');
    }
    foreach ($administrators as $operator) {
        $username = strtolower($operator['username'] ?? '');
        $name = $operator['name'] ?? '';
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D', $username)) {
            throw new RuntimeException('An intended administrator username is invalid.');
        }
        $operatorPassword = bin2hex(random_bytes(24));
        $user = app(AccountService::class)->create(['name' => $name, 'email' => $operator['email'], 'password' => $operatorPassword, 'role' => 'admin', 'must_change_password' => true]);
        $user->forceFill(['username' => $username])->save();
        $firstAdmin ??= $user->fresh();
        $accounts[] = ['name' => $name, 'username' => $username, 'email' => $operator['email'], 'role' => 'admin', 'password' => $operatorPassword, 'must_change_password' => true];
    }
    $gatePassword = bin2hex(random_bytes(24));
    app(GateManagement::class)->save($firstAdmin, ['code' => 'GATE-01', 'name' => 'Gate 1', 'status' => 'active', 'username' => 'gate1', 'password' => $gatePassword]);
    $accounts[] = ['name' => 'Gate 1', 'username' => 'gate1', 'role' => 'guard', 'password' => $gatePassword, 'must_change_password' => false];
    $runtime = ['connection' => PostgresSchema::runtimeConfig($c, $role, $password), 'schema' => 'nfcheck_online', 'app_key' => 'base64:'.base64_encode(random_bytes(32)), 'connection_fingerprint' => $fingerprint];
    foreach ([$runtimePath => $runtime, $accountsPath => ['workspace' => 'online evaluation', 'accounts' => $accounts]] as $path => $value) {
        $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Private handoff could not be saved.');
        }
        $createdFiles[] = $path;
        try {
            if (fwrite($handle, $bytes) !== strlen($bytes) || ! fflush($handle)) {
                throw new RuntimeException('Private handoff could not be saved completely.');
            }
        } finally {
            fclose($handle);
        }
    }
    $commitStarted = true;
    $connection->commit();
    $createdFiles = [];
    PostgresCloud::configure($runtime['connection'], 'nfcheck_online');
    foreach ($accounts as $account) {
        if (! Hash::check($account['password'], User::where('username', $account['username'])->sole()->password)) {
            throw new RuntimeException('An online handoff password did not match its saved hash.');
        }
    }
    $result = ['initialized' => true, 'accounts_verified' => count($accounts), 'people' => DB::table('persons')->count(), 'credentials' => DB::table('nfc_credentials')->count(), 'passages' => DB::table('access_events')->count(), 'private_handoff' => '.tools/secrets/cloud-operator-accounts.json'];
} catch (Throwable $error) {
    if (! $commitStarted && DB::transactionLevel() > 0) {
        DB::rollBack(0);
    }
    if (! $commitStarted) {
        foreach ($createdFiles as $ownedFile) {
            unlink($ownedFile);
        }
    } elseif ($createdFiles) {
        $result['commit_requires_read_only_recovery'] = true;
    }
    $result['error_type'] = get_class($error);
    if ($error instanceof QueryException) {
        $result['sql_state'] = $error->errorInfo[0] ?? null;
        $result['query_template'] = $error->getSql();
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['initialized'] ? 0 : 1);
