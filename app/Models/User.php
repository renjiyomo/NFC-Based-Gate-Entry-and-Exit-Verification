<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    public const DELETED_AT = 'archived_at';

    protected $fillable = ['name', 'email', 'password', 'role', 'status', 'person_id', 'must_change_password'];

    protected $hidden = ['password', 'remember_token', 'auth_version'];

    protected $attributes = ['status' => 'active', 'auth_version' => 1, 'record_version' => 1, 'must_change_password' => false];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'role' => UserRole::class, 'status' => RecordStatus::class,
            'auth_version' => 'integer', 'record_version' => 'integer', 'archived_at' => 'immutable_datetime', 'must_change_password' => 'boolean', 'last_login_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty(['name', 'email', 'role', 'status']) && ! $user->isDirty('record_version')) {
                $user->record_version = (int) $user->getOriginal('record_version') + 1;
            }
            if ($user->isDirty(['password', 'role', 'status'])) {
                $user->auth_version = (int) $user->getOriginal('auth_version') + 1;
            }
        });
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function isActive(): bool
    {
        return ! $this->trashed() && $this->status === RecordStatus::Active;
    }

    public function isAdmin(): bool
    {
        return $this->isActive() && $this->role === UserRole::Admin;
    }

    public function isGuard(): bool
    {
        return $this->isActive() && $this->role === UserRole::Guard;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function guardAssignments(): HasMany
    {
        return $this->hasMany(GuardGateAssignment::class, 'guard_id');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
