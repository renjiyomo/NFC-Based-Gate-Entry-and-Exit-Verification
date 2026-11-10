<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AutomaticScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isGuard();
    }

    public function rules(): array
    {
        abort_unless($this->expectsJson(), 406);

        return ['payload' => ['required', 'string', 'max:256'], 'request_id' => ['required', 'uuid'], 'direction' => ['prohibited'], 'person_id' => ['prohibited'], 'source' => ['prohibited']];
    }
}
