<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SummaryTask extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['claimed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $t): void {
            if (array_diff(array_keys($t->getDirty()), ['claimed_at']) || $t->getRawOriginal('claimed_at') !== null || ! $t->claimed_at) {
                throw new \LogicException('A summary task can be claimed only once.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Task history must be retained.'));
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SummaryRequest::class, 'summary_request_id');
    }
}
