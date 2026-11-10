<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.workspace');
    }

    protected function prepareForValidation(): void
    {
        // Credential writes are JSON-only, so invalid input is never flashed into a session.
        abort_unless($this->expectsJson(), 406, 'Credential requests must accept JSON.');
    }

    protected function getRedirectUrl(): string
    {
        return route('credentials.index');
    }

    public function rules(): array
    {
        if ($this->routeIs('*.issue')) {
            return ['person_id' => ['required', 'integer', Rule::exists('persons', 'id')->whereNull('archived_at')->where('status', 'active')], 'record_version' => ['required', 'integer', 'min:1'],
                'label' => ['prohibited'],
                'mode' => ['required', Rule::in(['new', 'replace', 'lost'])], 'replaces_id' => ['nullable', 'required_unless:mode,new', 'uuid'], 'request_id' => ['required', 'uuid'],
                'valid_until' => ['nullable', 'date_format:Y-m-d\TH:i', 'before:2100-01-01']];
        }
        if ($this->routeIs('*.read-back', '*.activate')) {
            return ['payload' => ['required', 'string', 'max:128'], 'completion_nonce' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D']];
        }
        if ($this->routeIs('*.check')) {
            return ['payload' => ['required', 'string', 'max:256']];
        }
        if ($this->routeIs('*.revoke')) {
            return ['reason' => ['required', Rule::in(['lost', 'damaged', 'withdrawn', 'security'])]];
        }

        return [];
    }
}
