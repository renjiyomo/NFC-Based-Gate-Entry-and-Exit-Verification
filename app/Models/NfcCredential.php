<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NfcCredential extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_digest', 'completion_digest', 'issuer_session_digest', 'issuer_auth_version', 'issuance_request_id'];

    protected static function booted(): void
    {
        static::updating(function (self $credential): void {
            foreach (['public_id', 'label', 'token_digest', 'source', 'created_by', 'issuer_auth_version', 'issuance_request_id', 'completion_digest', 'issuer_session_digest', 'person_version', 'issuance_mode', 'replaces_credential_id', 'pending_expires_at', 'valid_until'] as $field) {
                if ($credential->isDirty($field)) {
                    throw new \LogicException('Credential issuance and ownership are immutable. Issue a replacement.');
                }
            }
            $before = $credential->getRawOriginal('status');
            if (in_array($before, ['revoked', 'cancelled', 'expired'], true) && $credential->isDirty()) {
                throw new \LogicException('A closed credential cannot be changed or reactivated.');
            }
            if ($before === 'active' && $credential->status === 'pending') {
                throw new \LogicException('An active credential cannot return to pending.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Credential history must be retained.'));
    }

    protected function casts(): array
    {
        return ['person_version' => 'integer', 'issuer_auth_version' => 'integer', 'pending_expires_at' => 'immutable_datetime', 'read_back_at' => 'immutable_datetime', 'activated_at' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(CredentialAssignment::class, 'credential_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_credential_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function effectiveStatus(): string
    {
        return ($this->status === 'pending' && $this->pending_expires_at->lessThanOrEqualTo(now())) || ($this->status === 'active' && $this->valid_until?->lessThanOrEqualTo(now())) ? 'expired' : $this->status;
    }
}
