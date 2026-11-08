<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessAuthorization extends Model
{
    protected $fillable = ['person_id', 'status', 'valid_from', 'valid_until', 'reason', 'granted_by', 'revoked_by', 'revoked_at'];

    protected function casts(): array
    {
        return ['valid_from' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function scopeEffective(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $query->where('status', 'active')->whereNull('revoked_at')->where('valid_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $at))
            ->whereHas('person', fn (Builder $q) => $q->where('status', 'active'));
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
