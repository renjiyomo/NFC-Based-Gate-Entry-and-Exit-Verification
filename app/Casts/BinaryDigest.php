<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class BinaryDigest implements CastsAttributes, ComparesCastableAttributes
{
    public static function bytes(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            $position = ftell($value);
            rewind($value);
            $bytes = stream_get_contents($value);
            fseek($value, $position);

            return $bytes;
        }

        return is_string($value) && preg_match('/^\\\\x[0-9a-f]{64}$/D', $value) ? hex2bin(substr($value, 2)) : $value;
    }

    public static function binding(string $value): string
    {
        return DB::getDriverName() === 'pgsql' ? '\\x'.bin2hex($value) : $value;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::bytes($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $value = self::bytes($value);
        if (! is_string($value) || strlen($value) !== 32) {
            throw new \LogicException('Credential digests must contain exactly 32 bytes.');
        }

        return $model->getConnection()->getDriverName() === 'pgsql' ? '\\x'.bin2hex($value) : $value;
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::bytes($firstValue) === self::bytes($secondValue);
    }
}
