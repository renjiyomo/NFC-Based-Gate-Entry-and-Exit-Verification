<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gate extends Model
{
    use SoftDeletes;

    public const DELETED_AT = 'archived_at';

    protected $fillable = ['code', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class, 'record_version' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GuardGateAssignment::class);
    }
}
