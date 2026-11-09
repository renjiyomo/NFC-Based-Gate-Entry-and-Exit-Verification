<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SummaryRequest extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'snapshot_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'actor_auth_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Summary provenance is immutable.'));
        static::deleting(fn () => throw new \LogicException('Summary history must be retained.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function task(): HasOne
    {
        return $this->hasOne(SummaryTask::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(SummaryResult::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SummaryReview::class)->orderByDesc('id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(SummaryReview::class)->latestOfMany();
    }
}
