<?php

declare(strict_types=1);

use App\Support\PostgresCloud;
use App\Support\PostgresRecovery;
use App\Support\PostgresSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\PostgresRaces;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$result = ['passed' => false, 'scope' => 'new disposable PostgreSQL schema only'];
$schema = 'nfcheck_probe_'.bin2hex(random_bytes(8));
$role = 'nfcheck_t_'.bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(24));
$created = false;
$roleCreated = false;
$admin = null;
$targetPath = $root.'/.tools/runtime/postgres-test-target.json';
$targetOwned = false;
try {
    if (is_file($targetPath)) {
        throw new RuntimeException('Another PostgreSQL validation target exists; review it before starting a new run.');
    }
    $c = PostgresCloud::load($root);
    $admin = PostgresCloud::connect($c);
    $result['server_version'] = $admin->query('SHOW server_version')->fetchColumn();
    $result['phase'] = 'create_disposable_schema';
    PostgresSchema::create($admin, $schema);
    $created = true;
    $result['phase'] = 'migrate';
    PostgresSchema::migrate($root, $c, $schema);
    $result['phase'] = 'harden';
    PostgresSchema::harden($admin, $schema);
    $result['phase'] = 'runtime_role';
    $admin->exec('CREATE ROLE '.PostgresSchema::identifier($role).' LOGIN NOINHERIT PASSWORD '.$admin->quote($password));
    $roleCreated = true;
    PostgresSchema::grantRuntime($admin, $schema, $role);
    $runtime = PostgresSchema::runtimeConfig($c, $role, $password);
    PostgresCloud::configure($runtime, $schema);
    $result['table_count'] = DB::selectOne('SELECT count(*) AS count FROM information_schema.tables WHERE table_schema = ?', [$schema])->count;
    $result['migration_count'] = DB::table('migrations')->count();
    $result['catalogues'] = [DB::table('campus_programs')->count(), DB::table('campus_departments')->count()];
    $result['phase'] = 'postgresql_workflow_tests';
    $token = bin2hex(random_bytes(24));
    $target = ['run_token' => $token, 'expires_at' => time() + 1800, 'connection' => $runtime, 'schema' => $schema, 'server_version' => $result['server_version'], 'app_key' => 'base64:'.base64_encode(random_bytes(32))];
    file_put_contents($targetPath, json_encode($target, JSON_THROW_ON_ERROR));
    $targetOwned = true;
    $tests = new Process([PHP_BINARY, $root.'/vendor/bin/phpunit', '-c', $root.'/phpunit.pgsql.xml'], $root, ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'NFCHECK_PG_TEST_RUN' => $token]);
    $tests->setTimeout(900);
    $tests->run();
    file_put_contents($root.'/.tools/artifacts/postgres-suite.log', $tests->getOutput().$tests->getErrorOutput());
    $result['postgresql_tests_passed'] = $tests->isSuccessful();
    if (! $tests->isSuccessful()) {
        throw new RuntimeException('PostgreSQL workflow tests failed; inspect the private test report.');
    }
    config(['app.key' => $target['app_key']]);
    $app->forgetInstance('encrypter');
    $result['phase'] = 'postgresql_races';
    // Workflow validation can outlive an idle pooler connection.
    $admin = PostgresCloud::connect($c);
    $result['races'] = PostgresRaces::run($root, $target, $admin);
    $result['phase'] = 'encrypted_restore_drill';
    $result['recovery'] = PostgresRecovery::drill($root, $c, $schema, $target['app_key']);
    $result['passed'] = true;
} catch (Throwable $error) {
    $result['error_type'] = get_class($error);
    if ($error instanceof QueryException) {
        $result['sql_state'] = $error->errorInfo[0] ?? null;
        $result['query_template'] = $error->getSql();
        if (($result['sql_state'] ?? '') === '08006') {
            $result['connection_diagnostic'] = str_replace(array_filter([$c['host'], $c['username'], $c['password'], $c['ca_file']]), '[redacted]', $error->getPrevious()?->getMessage() ?? '');
        }
    } elseif ($error instanceof PDOException) {
        $result['sql_state'] = $error->getCode();
    }
} finally {
    if ($targetOwned) {
        unlink($targetPath);
    }
    DB::disconnect('pgsql');
    if ($created) {
        try {
            PostgresSchema::dropProbe($admin, $schema);
            $result['owned_schema_removed'] = true;
        } catch (Throwable) {
            $result['owned_schema_removed'] = false;
        }
    }
    if ($roleCreated) {
        try {
            $admin->exec('DROP ROLE '.PostgresSchema::identifier($role));
            $result['owned_role_removed'] = true;
        } catch (Throwable) {
            $result['owned_role_removed'] = false;
        }
    }
}
file_put_contents($root.'/.tools/artifacts/postgres-validation.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['passed'] && ($result['owned_schema_removed'] ?? false) && ($result['owned_role_removed'] ?? false) ? 0 : 1);
