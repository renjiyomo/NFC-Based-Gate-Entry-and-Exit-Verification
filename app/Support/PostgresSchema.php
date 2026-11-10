<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use PDO;

final class PostgresSchema
{
    public static function identifier(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $name) !== 1) {
            throw new \RuntimeException('Unexpected PostgreSQL identifier.');
        }

        return '"'.$name.'"';
    }

    public static function create(PDO $admin, string $schema): void
    {
        if (preg_match('/^nfcheck_(online|probe_[a-f0-9]{16})$/D', $schema) !== 1) {
            throw new \RuntimeException('Unrecognized new schema target.');
        }
        // CREATE without IF NOT EXISTS deliberately refuses any existing schema.
        $s = self::identifier($schema);
        $admin->exec("CREATE SCHEMA $s");
        $admin->exec("REVOKE ALL ON SCHEMA $s FROM PUBLIC, anon, authenticated");
    }

    public static function migrate(string $root, array $c, string $schema): void
    {
        PostgresCloud::configure($c, $schema);
        CloudMigrationTarget::authorize($root, $schema);
        try {
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new \RuntimeException('Cloud schema migration failed; no application data was imported.');
            }
        } finally {
            CloudMigrationTarget::clear();
        }
    }

    public static function harden(PDO $admin, string $schema): void
    {
        $columns = $admin->prepare('SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = ? ORDER BY table_name, ordinal_position');
        $columns->execute([$schema]);
        $clauses = [];
        foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $table = $column['table_name'];
            $name = $column['column_name'];
            $quoted = self::identifier($name);
            if (in_array($column['data_type'], ['smallint', 'integer', 'bigint'], true)) {
                $constraint = self::identifier('nfcheck_nonnegative_'.substr(hash('sha256', $table.'.'.$name), 0, 12));
                $clauses[$table][] = "ADD CONSTRAINT $constraint CHECK ($quoted >= 0)";
            }
            if ($column['data_type'] === 'bytea') {
                $constraint = self::identifier('nfcheck_digest_'.substr(hash('sha256', $table.'.'.$name), 0, 12));
                $clauses[$table][] = "ADD CONSTRAINT $constraint CHECK (octet_length($quoted) = 32)";
            }
        }
        foreach ($clauses as $table => $parts) {
            $admin->exec('ALTER TABLE '.self::identifier($schema).'.'.self::identifier($table).' '.implode(', ', $parts));
        }
        // MySQL identifiers were case-insensitive. Preserve that uniqueness rule
        // without making opaque NFC digests or UUID comparisons case-insensitive.
        foreach (['users_username' => ['users', 'lower(username)'], 'users_email' => ['users', 'lower(email)'], 'persons_number' => ['persons', 'person_type, lower(campus_number)'], 'gates_code' => ['gates', 'lower(code)'], 'credential_label' => ['nfc_credentials', 'lower(label)']] as $index => [$table, $expression]) {
            $admin->exec('CREATE UNIQUE INDEX '.self::identifier('nfcheck_ci_'.$index).' ON '.self::identifier($schema).'.'.self::identifier($table).' ('.$expression.')');
        }
    }

    public static function grantRuntime(PDO $admin, string $schema, string $role): void
    {
        $s = self::identifier($schema);
        $r = self::identifier($role);
        $admin->exec("GRANT USAGE ON SCHEMA $s TO $r");
        $tables = $admin->prepare('SELECT tablename FROM pg_tables WHERE schemaname = ? ORDER BY tablename');
        $tables->execute([$schema]);
        foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $name = $s.'.'.self::identifier($table);
            $permissions = match ($table) {
                'audit_logs', 'scan_attempts', 'access_events', 'passage_corrections', 'anomaly_flags', 'anomaly_evidence', 'anomaly_reviews', 'summary_requests', 'summary_results', 'summary_reviews', 'summary_tasks' => 'SELECT, INSERT',
                'summary_controls', 'migrations', 'campus_programs', 'campus_departments' => 'SELECT',
                default => 'SELECT, INSERT, UPDATE, DELETE',
            };
            $admin->exec("REVOKE ALL ON TABLE $name FROM PUBLIC, anon, authenticated");
            $admin->exec("GRANT $permissions ON TABLE $name TO $r");
            $updates = match ($table) {
                'scan_attempts' => 'review_status, reviewed_at, reviewed_by, review_reason, duplicate_of_event_id, updated_at',
                'summary_tasks' => 'claimed_at', 'summary_controls' => 'quota_day, claims', default => null,
            };
            if ($updates) {
                $admin->exec("GRANT UPDATE ($updates) ON TABLE $name TO $r");
            }
        }
        $admin->exec("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA $s TO $r");
    }

    public static function runtimeConfig(array $maintenance, string $role, string $password): array
    {
        $suffix = str_contains($maintenance['username'], '.') ? substr($maintenance['username'], strpos($maintenance['username'], '.')) : '';

        return [...$maintenance, 'username' => $role.$suffix, 'password' => $password];
    }

    public static function dropProbe(PDO $admin, string $schema): void
    {
        if (preg_match('/^nfcheck_probe_[a-f0-9]{16}$/D', $schema) !== 1) {
            throw new \RuntimeException('Only the exact owned probe schema may be removed.');
        }
        $admin->exec('DROP SCHEMA '.self::identifier($schema).' CASCADE');
    }
}
