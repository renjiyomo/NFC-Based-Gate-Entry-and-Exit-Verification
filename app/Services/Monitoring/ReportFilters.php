<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ReportFilters
{
    public CarbonImmutable $from;

    public CarbonImmutable $until;

    public CarbonImmutable $asOf;

    public array $values;

    public function __construct(array $input = [], ?CarbonImmutable $asOf = null)
    {
        $data = Validator::make($input, [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'custom'])],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'after_or_equal:2000-01-01'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'after_or_equal:from', 'before:2100-01-01'],
            'q' => ['nullable', 'string', 'max:100', 'not_regex:/nfcheck:v[0-9]+:/i'],
            'kind' => ['nullable', Rule::in(['student', 'employee'])],
            'gate_id' => ['nullable', 'integer', 'min:1', 'exists:gates,id'],
            'guard_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'direction' => ['nullable', Rule::in(['entry', 'exit'])],
            'view' => ['nullable', Rule::in(['passages', 'attempts'])],
            'outcome' => ['nullable', Rule::in(['allowed', 'denied'])],
            'review' => ['nullable', Rule::in(['pending', 'confirmed', 'cancelled', 'expired', 'identity_mismatch', 'not_applicable', 'duplicate'])],
            'validity' => ['nullable', Rule::in(['effective', 'all', 'voided'])],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'source' => ['prohibited'],
        ])->validate();
        $this->asOf = $asOf ?? CarbonImmutable::now('UTC');
        $period = $data['period'] ?? 'daily';
        $date = CarbonImmutable::parse($data['date'] ?? $this->asOf->setTimezone('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        [$start, $end] = match ($period) {
            'weekly' => [$date->startOfWeek(), $date->startOfWeek()->addWeek()],
            'monthly' => [$date->startOfMonth(), $date->startOfMonth()->addMonth()],
            'custom' => [CarbonImmutable::parse($data['from'], 'Asia/Manila')->startOfDay(), CarbonImmutable::parse($data['to'], 'Asia/Manila')->startOfDay()->addDay()],
            default => [$date, $date->addDay()],
        };
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a period of at most 366 days.']);
        }
        $this->from = $start->utc();
        $this->until = $end->utc();
        $this->values = ['period' => $period, 'date' => $date->toDateString(), 'from' => $start->toDateString(), 'to' => $end->subDay()->toDateString(), 'q' => '', 'kind' => '', 'gate_id' => '', 'guard_id' => '', 'direction' => '', 'view' => 'passages', 'outcome' => '', 'review' => '', 'validity' => 'effective', 'per_page' => 25, ...array_filter($data, fn ($v) => $v !== null), 'from' => $start->toDateString(), 'to' => $end->subDay()->toDateString()];
    }

    public static function request(Request $request): self
    {
        return new self($request->query());
    }

    public function cutoff(): CarbonImmutable
    {
        return $this->until->min($this->asOf);
    }

    public function metadata(): array
    {
        return ['timezone' => 'Asia/Manila', 'from' => $this->values['from'], 'to' => $this->values['to'], 'as_of' => $this->asOf->toIso8601String()];
    }
}
