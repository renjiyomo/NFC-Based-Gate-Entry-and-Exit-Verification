<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gate extends Model
{
    protected $fillable = ['code', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GuardGateAssignment::class);
    }
}
