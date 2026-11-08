<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAssignment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            if ($assignment->isDirty(['person_id', 'credential_id', 'assigned_by', 'assigned_at'])) {
                throw new \DomainException('Credential ownership is permanent.');
            }
        });
        static::deleting(fn () => throw new \DomainException('Credential assignment history must be retained.'));
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withTrashed();
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(NfcCredential::class);
    }
}
