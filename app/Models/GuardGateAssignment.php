<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardGateAssignment extends Model
{
    protected $fillable = ['guard_id', 'gate_id', 'starts_at', 'ends_at', 'assigned_by', 'revoked_by', 'revoked_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (GuardGateAssignment $assignment): void {
            if (User::find($assignment->guard_id)?->role !== UserRole::Guard) {
                throw new \DomainException('Only guard accounts can receive gate assignments.');
            }
        });
    }

    public function scopeEffective(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $query->whereNull('revoked_at')->where('starts_at', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id');
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
