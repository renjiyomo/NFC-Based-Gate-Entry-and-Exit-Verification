<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$workspace = $argv[1] ?? '';
if (! in_array($workspace, ['simulator', 'hardware'], true)) {
    throw new RuntimeException('Choose simulator or hardware. The main workspace is not approved for external AI.');
}
$values = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.'.$workspace));
if (($values['DB_DATABASE'] ?? '') !== 'nfcheck_'.$workspace || ($values['DB_USERNAME'] ?? '') !== 'nfcheck_'.$workspace || ($values['APP_ENV'] ?? '') !== 'local' || ($values['DB_HOST'] ?? '') !== '127.0.0.1' || ($values['DB_PORT'] ?? '') !== '3307') {
    throw new RuntimeException('Refusing an unrecognized summary worker environment.');
}
$watch = ($argv[2] ?? '') === '--watch';
$args = [PHP_BINARY, $root.'/artisan', 'nfcheck:work-summaries', '--limit=1'];
if ($watch) {
    $args[] = '--watch';
}
$p = new Process($args, $root, $values);
$p->setTimeout($watch ? null : 45);
$p->run(fn (string $type, string $text) => fwrite($type === Process::ERR ? STDERR : STDOUT, $text));
exit($p->getExitCode() ?? 1);
