<?php

namespace App\Services;

use App\Models\Gate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GateManagement
{
    public function __construct(private ManagementGuard $guard, private AuditLogger $audit) {}

    public function save(User $actor, array $data, ?Gate $target = null): Gate
    {
        return DB::transaction(function () use ($actor, $data, $target): Gate {
            $actor = $this->guard->actor($actor);
            $gate = $target ? Gate::withTrashed()->lockForUpdate()->findOrFail($target->id) : new Gate;
            if ($target) {
                $this->guard->version($gate, (int) $data['record_version']);
                $this->guard->editable($gate);
            }
            $gate->fill(Arr::only($data, ['code', 'name', 'status']));
            $gate->forceFill(['record_version' => $target ? (int) $gate->record_version + 1 : 1])->save();
            if (isset($data['username'])) {
                app(GateAccounts::class)->save($actor, $gate, $data);
            }
            $this->audit->record($target ? 'gate.updated' : 'gate.created', $actor, $gate, ['status' => $gate->status->value]);

            return $gate;
        }, 3);
    }

    public function archive(User $actor, Gate $target, int $version, bool $restore = false): Gate
    {
        return DB::transaction(function () use ($actor, $target, $version, $restore): Gate {
            $actor = $this->guard->actor($actor);
            $gate = Gate::withTrashed()->lockForUpdate()->findOrFail($target->id);
            $this->guard->version($gate, $version);
            if ($restore ? ! $gate->trashed() : $gate->trashed()) {
                return $gate;
            }
            $gate->forceFill(['status' => 'inactive', 'record_version' => (int) $gate->record_version + 1])->save();
            if ($gate->account) {
                $gate->account->status = 'inactive';
                $gate->account->save();
            }
            if ($restore) {
                $gate->restore();
            } else {
                $gate->assignments()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
                $gate->delete();
            }
            $this->audit->record($restore ? 'gate.restored' : 'gate.archived', $actor, $gate);

            return $gate;
        }, 3);
    }
}
