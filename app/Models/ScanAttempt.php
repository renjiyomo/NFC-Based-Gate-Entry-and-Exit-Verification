<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScanAttempt extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected $hidden = ['session_digest', 'payload_fingerprint', 'guard_auth_version'];

    protected function casts(): array
    {
        return ['guard_auth_version' => 'integer', 'person_version' => 'integer', 'duplicate_window_seconds' => 'integer', 'received_at' => 'immutable_datetime', 'review_expires_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            $allowed = ['review_status', 'reviewed_at', 'reviewed_by', 'review_reason', 'duplicate_of_event_id', 'updated_at'];
            if (array_diff(array_keys($attempt->getDirty()), $allowed) || ($attempt->isDirty() && $attempt->getRawOriginal('review_status') !== 'pending') || $attempt->review_status === 'pending') {
                throw new \LogicException('Verification facts are immutable; reviews have one terminal transition.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Verification history must be retained.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withTrashed();
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(NfcCredential::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class)->withTrashed();
    }

    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id')->withTrashed();
    }

    public function passage(): HasOne
    {
        return $this->hasOne(AccessEvent::class);
    }

    public function duplicate(): BelongsTo
    {
        return $this->belongsTo(AccessEvent::class, 'duplicate_of_event_id');
    }

    public function effectiveReviewStatus(): string
    {
        return $this->review_status === 'pending' && $this->review_expires_at->lessThanOrEqualTo(now()) ? 'expired' : $this->review_status;
    }
}
