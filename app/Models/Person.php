<?php

namespace App\Models;

use App\Enums\PersonType;
use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    public const DELETED_AT = 'archived_at';

    protected $table = 'persons';

    protected $fillable = ['person_type', 'campus_number', 'full_name', 'photo_path', 'status'];

    protected function casts(): array
    {
        return ['person_type' => PersonType::class, 'status' => RecordStatus::class, 'record_version' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Person $person): void {
            if ($person->isDirty('person_type')) {
                throw new \DomainException('Person classification requires a dedicated reviewed workflow.');
            }
        });
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(AccessAuthorization::class);
    }

    public function operator(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
