<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$values = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.simulator'));
if (($values['DB_DATABASE'] ?? '') !== 'nfcheck_simulator' || ($values['APP_ENV'] ?? '') !== 'local' || ($values['NFCHECK_NFC_SIMULATOR'] ?? '') !== 'true') {
    throw new RuntimeException('Refusing to start an unisolated simulator.');
}
$process = new Process([PHP_BINARY, '-S', '127.0.0.1:8002', '-t', $root.'/public', $root.'/server.php'], $root.'/public', $values);
$process->setTimeout(null);
$process->run(fn (string $type, string $buffer) => fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer));
exit($process->getExitCode() ?? 1);
