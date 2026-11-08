<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(string $action, ?User $actor = null, ?Model $target = null, array $metadata = [], string $outcome = 'success'): AuditLog
    {
        $safe = array_intersect_key($metadata, array_flip(['reason', 'role', 'status', 'source', 'subject_hash', 'ip_hash']));

        return AuditLog::create(['actor_id' => $actor?->id, 'action' => $action, 'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(), 'outcome' => $outcome, 'metadata' => $safe ?: null]);
    }

    public function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
