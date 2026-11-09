<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnomalyFlag extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['policy' => 'array', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Detection facts are immutable.'));
        static::deleting(fn () => throw new \LogicException('Detection history must be retained.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function anchor(): BelongsTo
    {
        return $this->belongsTo(ScanAttempt::class, 'anchor_attempt_id');
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(ScanAttempt::class, 'anomaly_evidence');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AnomalyReview::class)->orderByDesc('id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(AnomalyReview::class)->latestOfMany();
    }
}
