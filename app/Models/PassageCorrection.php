<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassageCorrection extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Corrections are append-only.'));
        static::deleting(fn () => throw new \LogicException('Corrections must be retained.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
