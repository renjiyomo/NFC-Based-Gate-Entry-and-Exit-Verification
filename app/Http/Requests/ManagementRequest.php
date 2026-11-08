<?php

namespace App\Http\Requests;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementRequest extends FormRequest
{
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
        foreach (['full_name', 'campus_number', 'program', 'academic_term', 'department', 'position', 'name', 'code', 'email', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
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
                'name' => $this->text(120), 'status' => ['required', Rule::in(['active', 'inactive'])], ...$this->versionRules()],
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

        return [...$rules, ...($student ? ['program' => $this->text(120), 'year_level' => ['nullable', 'integer', 'min:1', 'max:255'], 'academic_term' => $this->text(50, false)] : ['department' => $this->text(120), 'position' => $this->text(120)])];
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
