<?php

namespace App\Support;

use App\Models\Gate;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DirectoryData
{
    public static function intervalState(CarbonInterface $start, ?CarbonInterface $end, ?CarbonInterface $revoked): string
    {
        return $revoked ? 'Revoked' : ($start->isFuture() ? 'Scheduled' : ($end && $end->lessThanOrEqualTo(now()) ? 'Expired' : 'Current'));
    }

    public static function filters(Request $request): array
    {
        $validator = Validator::make($request->query(), ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'records' => ['nullable', Rule::in(['current', 'archived', 'all'])], 'group' => ['nullable', 'string', 'max:120'], 'access' => ['nullable', Rule::in(['authorized', 'not_authorized'])],
            'year_level' => ['nullable', 'string', 'max:16'], 'gate_id' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', Rule::in([15, 30, 50])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        abort_if($validator->fails(), 422, 'Invalid directory filters.');

        return ['q' => '', 'status' => '', 'records' => 'current', 'group' => '', 'year_level' => '', 'access' => '', 'gate_id' => '', 'per_page' => 15, ...array_filter(array_diff_key($validator->validated(), ['page' => true]), fn ($value) => $value !== null)];
    }

    public static function records(Builder $query, array $filters): Builder
    {
        if ($filters['records'] === 'archived') {
            $query->onlyTrashed();
        }
        if ($filters['records'] === 'all') {
            $query->withTrashed();
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    public static function like(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }

    public static function person(Person $person): array
    {
        $profile = $person->person_type->value === 'student' ? $person->studentProfile : $person->employeeProfile;
        $assignment = $person->relationLoaded('activeCredentialAssignment') ? $person->activeCredentialAssignment : null;
        $credential = $assignment && ! $assignment->ended_at ? $assignment->credential : null;

        return ['id' => $person->id, 'kind' => $person->person_type->value, 'campus_number' => $person->campus_number, 'full_name' => $person->full_name,
            'status' => $person->status->value, 'record_version' => (int) $person->record_version, 'archived_at' => $person->archived_at?->toIso8601String(),
            'photo_url' => $person->photo_path ? '/admin/'.($person->person_type->value === 'student' ? 'students' : 'employees').'/'.$person->id.'/photo?v='.$person->record_version : null,
            'program' => $profile?->program, 'year_level' => $profile?->year_level, 'academic_term' => $profile?->academic_term, 'academic_year' => $profile?->academic_year, 'semester' => $profile?->semester, 'department' => $profile?->department, 'position' => $profile?->position,
            'access_active' => (bool) ($person->access_active ?? false),
            'nfc_credential' => $credential?->effectiveStatus() === 'active' ? ['label' => $credential->label, 'status' => 'active'] : null];
    }

    public static function gate(Gate $gate): array
    {
        return ['id' => $gate->id, 'code' => $gate->code, 'name' => $gate->name, 'status' => $gate->status->value, 'record_version' => (int) $gate->record_version,
            'archived_at' => $gate->archived_at?->toIso8601String(), 'assignments_count' => $gate->assignments_count ?? 0, 'account_id' => $gate->account?->id, 'username' => $gate->account?->username, 'account_status' => $gate->account?->status->value];
    }

    public static function guard(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'username' => $user->username, 'gate_id' => $user->gate_id, 'status' => $user->status->value, 'record_version' => (int) $user->record_version,
            'archived_at' => $user->archived_at?->toIso8601String(), 'must_change_password' => $user->must_change_password, 'assignments_count' => $user->guard_assignments_count ?? 0];
    }
}
