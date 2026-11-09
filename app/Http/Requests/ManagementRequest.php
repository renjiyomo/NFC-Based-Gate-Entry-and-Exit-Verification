<?php

namespace App\Http\Requests;

use App\Rules\NotCommonPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementRequest extends FormRequest
{
    public function attributes(): array
    {
        return ['campus_number' => 'university number'];
    }

    public function run(callable $action): mixed
    {
        try {
            return $action();
        } catch (UniqueConstraintViolationException) {
            $field = $this->route('directory') ? 'campus_number' : ($this->routeIs('admin.guards.*') ? 'email' : 'code');
            throw ValidationException::withMessages([$field => 'This identifier is already in use, including archived records.'])->redirectTo($this->getRedirectUrl());
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($this->getRedirectUrl());
        }
    }

    public function authorize(): bool
    {
        if (! Gate::allows('admin.workspace')) {
            return false;
        }
        if ($person = $this->route('person')) {
            abort_unless($person->person_type->value === ($this->route('directory') === 'students' ? 'student' : 'employee'), 404);
        }
        if ($guard = $this->route('guard')) {
            Gate::authorize('manageGuard', $guard);
        }

        return true;
    }

    protected function getRedirectUrl(): string
    {
        $parameters = $this->route()->parameters();
        if (isset($parameters['person'])) {
            return '/admin/'.$this->route('directory').'/'.$this->route('person')->id.($this->routeIs('admin.people.update', 'admin.gates.update', 'admin.guards.update') ? '/edit' : '');
        }
        if (isset($parameters['gate'])) {
            return '/admin/gates/'.$this->route('gate')->id.($this->routeIs('admin.people.update', 'admin.gates.update', 'admin.guards.update') ? '/edit' : '');
        }
        if (isset($parameters['guard'])) {
            return '/admin/guards/'.$this->route('guard')->id.($this->routeIs('admin.people.update', 'admin.gates.update', 'admin.guards.update') ? '/edit' : '');
        }

        return '/admin/'.($this->route('directory') ?: ($this->routeIs('admin.guards.*') ? 'guards' : 'gates')).'/create';
    }

    protected function prepareForValidation(): void
    {
        foreach (['full_name', 'campus_number', 'program', 'academic_term', 'department', 'position', 'name', 'code', 'email', 'username', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('admin.people.store', 'admin.people.update') => $this->personRules(),
            $this->routeIs('admin.gates.store', 'admin.gates.update') => [
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/D', Rule::unique('gates')->ignore($this->route('gate')?->id)],
                'name' => $this->text(120), 'status' => ['required', Rule::in(['active', 'inactive'])], ...$this->gateAccountRules(), ...$this->versionRules()],
            $this->routeIs('admin.guards.store') => ['name' => $this->text(120), 'email' => ['required', 'email', 'max:255', Rule::unique('users')]],
            $this->routeIs('admin.guards.update') => ['name' => $this->text(120), 'status' => ['required', Rule::in(['active', 'inactive'])], ...$this->versionRules()],
            $this->routeIs('admin.people.grants.store') => [...$this->timeRules(), 'reason' => $this->text(255), ...$this->versionRules()],
            $this->routeIs('admin.assignments.store', 'admin.assignments.update', 'admin.guards.assignments.store', 'admin.guards.assignments.update') => [...$this->timeRules(), 'guard_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'guard')->where('status', 'active')->whereNull('archived_at')], 'gate_id' => ['required', 'integer', Rule::exists('gates', 'id')->where('status', 'active')->whereNull('archived_at')]],
            $this->routeIs('*.grants.destroy', '*.assignments.destroy', 'admin.assignments.destroy', 'admin.guards.setup') => [],
            default => ['record_version' => ['required', 'integer', 'min:1']],
        };
    }

    private function personRules(): array
    {
        $student = $this->route('directory') === 'students';
        $rules = ['campus_number' => [...$this->text(64), Rule::unique('persons')->where('person_type', $student ? 'student' : 'employee')->ignore($this->route('person')?->id)],
            'full_name' => $this->text(120), 'status' => ['required', Rule::in(['active', 'inactive'])],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096'],
            'remove_photo' => ['sometimes', 'boolean'], ...$this->versionRules()];

        return [...$rules, ...($student ? ['program' => ['required', 'string', 'max:32', Rule::exists('campus_programs', 'code')], 'year_level' => ['required', 'integer', 'min:1', 'max:'.(DB::table('campus_programs')->where('code', is_string($this->input('program')) ? $this->input('program') : '')->value('max_year_level') ?? 4)], 'academic_year' => ['required', 'string', 'regex:/^20[0-9]{2}-20[0-9]{2}$/D', function ($attribute, $value, $fail) {
            if (! is_string($value) || (int) substr($value, 5) !== (int) substr($value, 0, 4) + 1) {
                $fail('Use consecutive academic years, for example 2026-2027.');
            }
        }], 'semester' => ['required', 'string', Rule::in(config('campus.semesters'))]] : ['department' => ['required', 'string', 'max:120', Rule::exists('campus_departments', 'name')], 'position' => $this->text(120)])];
    }

    private function gateAccountRules(): array
    {
        $account = $this->route('gate')?->account;

        return ['username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/D', Rule::unique('users')->ignore($account?->id)],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:15', 'max:128', 'confirmed', new NotCommonPassword],
            'account_status' => ['sometimes', Rule::in(['active', 'inactive'])]];
    }

    private function versionRules(): array
    {
        return $this->routeIs('*.update', '*.grants.store') ? ['record_version' => ['required', 'integer', 'min:1']] : [];
    }

    private function text(int $max, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[\p{C}]/u'];
    }

    private function timeRules(): array
    {
        return ['starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:2000-01-01', 'before:2100-01-01'], 'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at', 'before:2100-01-01']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->boolean('remove_photo') && $this->hasFile('photo')) {
                $validator->errors()->add('photo', 'Choose a replacement photo or remove the existing photo, not both.');
            }
        }];
    }
}
