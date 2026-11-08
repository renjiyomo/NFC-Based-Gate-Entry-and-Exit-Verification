<?php

namespace App\Models;

use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $primaryKey = 'person_id';

    public $incrementing = false;

    protected $fillable = ['person_id', 'department', 'position'];

    protected static function booted(): void
    {
        static::saving(function (EmployeeProfile $profile): void {
            if (Person::find($profile->person_id)?->person_type !== PersonType::Employee) {
                throw new \DomainException('An employee profile requires an employee identity.');
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
