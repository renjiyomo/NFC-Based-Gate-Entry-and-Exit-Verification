<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SummaryResult extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['selected_facts' => 'array', 'sentences' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Summary outcomes are immutable.'));
        static::deleting(fn () => throw new \LogicException('Summary outcomes must be retained.'));
    }
}
