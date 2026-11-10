<?php

namespace Tests\Postgres;

use App\Services\Credentials\CredentialLabels;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

class CredentialLabelsTest extends PostgresTestCase
{
    public function test_separate_counters_rollback_and_overflow_padding(): void
    {
        $labels = app(CredentialLabels::class);
        $this->assertSame('TAG-STUDENT-001', $labels->next('student'));
        $this->assertSame('TAG-EMPLOYEE-001', $labels->next('employee'));
        DB::table('credential_counters')->where('kind', 'student')->update(['last_number' => 999]);
        $this->assertSame('TAG-STUDENT-1000', $labels->next('student'));
        try {
            DB::transaction(function () use ($labels) {
                $labels->next('employee');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('TAG-EMPLOYEE-002', $labels->next('employee'));
    }
}
