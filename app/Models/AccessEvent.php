<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class)->withTrashed();
    }

    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id')->withTrashed();
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PassageCorrection::class)->orderBy('id');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(PassageCorrection::class)->ofMany(['id' => 'max'], fn ($q) => $q->whereIn('action', ['void', 'reinstate']));
    }

    public function scopeEffective(Builder $query): void
    {
        $query->whereNotExists(function ($void): void {
            $void->selectRaw('1')->from('passage_corrections as pc')->whereColumn('pc.access_event_id', 'access_events.id')->where('pc.action', 'void')
                ->whereNotExists(fn ($later) => $later->selectRaw('1')->from('passage_corrections as newer')->whereColumn('newer.access_event_id', 'pc.access_event_id')->whereIn('newer.action', ['void', 'reinstate'])->whereColumn('newer.id', '>', 'pc.id'));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
