<?php

namespace Tests\Feature;

use App\Models\NfcCredential;
use App\Services\Credentials\CredentialLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\ManagementTestCase;

class CredentialLabelsTest extends ManagementTestCase
{
    private function issuePerson($person): array
    {
        return app(CredentialLifecycle::class)->issue($this->admin, ['person_id' => $person->id, 'record_version' => $person->fresh()->record_version, 'mode' => 'new', 'request_id' => (string) Str::uuid()], 'labels', 'physical');
    }

    public function test_separate_sequences_and_no_reuse_after_cancellation(): void
    {
        $first = $this->issuePerson($this->makePerson());
        $this->assertSame('TAG-STUDENT-001', $first['credential']['label']);
        $employee = $this->issuePerson($this->makePerson('employees'));
        $this->assertSame('TAG-EMPLOYEE-001', $employee['credential']['label']);
        app(CredentialLifecycle::class)->terminate($this->admin, NfcCredential::where('public_id', $first['credential']['id'])->sole(), 'cancel', 'issuance_cancelled');
        $this->assertSame('TAG-STUDENT-002', $this->issuePerson($this->makePerson())['credential']['label']);
    }

    public function test_failed_issuance_rolls_counter_back_and_numbers_extend_past_999(): void
    {
        DB::table('credential_counters')->where('kind', 'student')->update(['last_number' => 999]);
        $person = $this->makePerson();
        try {
            DB::transaction(function () use ($person) {
                $this->issuePerson($person);
                throw new \RuntimeException('rollback fixture');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(999, (int) DB::table('credential_counters')->where('kind', 'student')->value('last_number'));
        $this->assertSame('TAG-STUDENT-1000', $this->issuePerson($person->fresh())['credential']['label']);
    }

    public function test_manual_http_labels_are_rejected_and_omitted_labels_are_generated(): void
    {
        $person = $this->makePerson();
        $body = ['person_id' => $person->id, 'record_version' => $person->record_version, 'mode' => 'new', 'request_id' => (string) Str::uuid()];
        $this->postJson('/admin/credentials/issue', [...$body, 'label' => 'TAG-STUDENT-900'])->assertUnprocessable()->assertJsonValidationErrors('label');
        $this->postJson('/admin/credentials/issue', $body)->assertCreated()->assertJsonPath('credential.label','TAG-STUDENT-001');
    }
}
