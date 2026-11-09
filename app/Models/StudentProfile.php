<?php

namespace App\Models;

use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $primaryKey = 'person_id';

    public $incrementing = false;

    protected $fillable = ['person_id', 'program', 'year_level', 'academic_term', 'academic_year', 'semester'];

    protected static function booted(): void
    {
        static::saving(function (StudentProfile $profile): void {
            if (Person::find($profile->person_id)?->person_type !== PersonType::Student) {
                throw new \DomainException('A student profile requires a student identity.');
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
