<?php

namespace Tests;

use App\Enums\PersonType;
use App\Models\Person;
use App\Models\User;
use App\Services\PersonManagement;

abstract class ManagementTestCase extends DatabaseTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user();
        $this->signIn($this->admin);
    }

    protected function student(array $overrides = []): array
    {
        return [...['campus_number' => 'S-'.bin2hex(random_bytes(5)), 'full_name' => 'Synthetic test student', 'status' => 'active', 'program' => 'BSCS', 'year_level' => 2, 'academic_year' => '2026-2027', 'semester' => '1st Semester', 'academic_term' => 'Test term'], ...$overrides];
    }

    protected function employee(array $overrides = []): array
    {
        return [...['campus_number' => 'E-'.bin2hex(random_bytes(5)), 'full_name' => 'Synthetic test employee', 'status' => 'active', 'department' => 'Computer Studies Department', 'position' => 'Test position'], ...$overrides];
    }

    protected function makePerson(string $directory = 'students', array $overrides = []): Person
    {
        $data = $directory === 'students' ? $this->student($overrides) : $this->employee($overrides);
        $type = $directory === 'students' ? PersonType::Student : PersonType::Employee;

        return app(PersonManagement::class)->save($this->admin, $type, $data)->fresh();
    }

    protected function schedule(array $overrides = []): array
    {
        return [...['starts_at' => now('Asia/Manila')->subMinute()->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Manila')->addHour()->format('Y-m-d\TH:i')], ...$overrides];
    }
}
