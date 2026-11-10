<?php

namespace App\Database;

final class PostgresConnector extends \Illuminate\Database\Connectors\PostgresConnector
{
    protected function addSslOptions($dsn, array $config)
    {
        // Detect broken TCP links as well as bounding initial connection setup.
        $config += ['connect_timeout' => 10, 'keepalives' => 1, 'keepalives_idle' => 10, 'keepalives_interval' => 5, 'keepalives_count' => 3, 'tcp_user_timeout' => 30000];
        foreach (['sslmode', 'sslcert', 'sslkey', 'sslrootcert', 'connect_timeout', 'keepalives', 'keepalives_idle', 'keepalives_interval', 'keepalives_count', 'tcp_user_timeout'] as $option) {
            if (isset($config[$option])) {
                // libpq requires quoted values when a Windows workspace has spaces.
                $value = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $config[$option]);
                $dsn .= ";$option='$value'";
            }
        }

        return $dsn;
    }
}
