<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GateOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isGuard();
    }

    protected function prepareForValidation(): void
    {
        abort_unless($this->expectsJson(), 406, 'Gate operations must accept JSON.');
    }

    public function rules(): array
    {
        return $this->routeIs('*.scan') ? ['payload' => ['required', 'string', 'max:256'], 'request_id' => ['required', 'uuid'], 'direction' => ['required', Rule::in(['entry', 'exit'])]] : ['action' => ['required', Rule::in(['confirm', 'cancel', 'identity_mismatch'])], 'identity_checked' => ['required_if:action,confirm', 'boolean', 'accepted_if:action,confirm'], 'identity_method' => ['required_if:action,confirm', Rule::in(['photo', 'campus_document'])]];
    }
}
