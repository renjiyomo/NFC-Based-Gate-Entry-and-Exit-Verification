<?php

namespace App\Services\Credentials;

use Illuminate\Support\Facades\DB;

final class CredentialLabels
{
    public function next(string $kind): string
    {
        if (DB::transactionLevel() < 1 || ! in_array($kind, ['student', 'employee'], true)) {
            throw new \LogicException('Credential numbering requires a valid person and transaction.');
        }
        $counter = DB::table('credential_counters')->where('kind', $kind)->lockForUpdate()->first();
        if (! $counter) {
            throw new \LogicException('Credential counter is not initialized.');
        }
        $number = (int) $counter->last_number;
        do {
            if ($number >= PHP_INT_MAX - 1) {
                throw new \LogicException('Credential counter is exhausted.');
            }
            $number++;
            $label = 'TAG-'.strtoupper($kind).'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        } while (DB::table('nfc_credentials')->whereRaw('LOWER(label) = ?', [strtolower($label)])->exists());
        DB::table('credential_counters')->where('kind', $kind)->update(['last_number' => $number]);

        return $label;
    }
}
