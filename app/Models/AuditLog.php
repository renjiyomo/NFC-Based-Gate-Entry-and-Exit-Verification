<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'target_type', 'target_id', 'outcome', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
