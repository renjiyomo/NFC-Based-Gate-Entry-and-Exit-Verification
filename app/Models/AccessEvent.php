<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessEvent extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Confirmed passage is immutable.'));
        static::deleting(fn () => throw new \LogicException('Confirmed passage must be retained.'));
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withTrashed();
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ScanAttempt::class, 'scan_attempt_id');
    }
}
