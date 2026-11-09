<?php

namespace App\Http\Controllers\GateOperations;

use App\Http\Middleware\EnsureNfcSimulator;
use App\Models\Gate;
use App\Models\ScanAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController
{
    public static function stations(User $user): array
    {
        return Gate::where('status', 'active')->where('id', $user->gate_id ?? 0)->orderBy('name')->limit(500)->get(['id', 'code', 'name'])->toArray();
    }

    public function scanner(Request $request, ?Gate $gate = null): Response
    {
        $gate ??= Gate::whereKey($request->user()->gate_id)->where('status', 'active')->first();
        $validator = Validator::make($request->query(), ['request_id' => ['nullable', 'uuid'], 'direction' => ['nullable', Rule::in(['entry', 'exit'])]]);
        abort_if($validator->fails(), 422, 'Invalid request locator.');

        return Inertia::render('operations/Scanner', ['stations' => self::stations($request->user()), 'station' => $gate ? ['id' => $gate->id, 'code' => $gate->code, 'name' => $gate->name] : null, 'requestId' => $gate ? ($validator->validated()['request_id'] ?? null) : null, 'requestDirection' => $validator->validated()['direction'] ?? null, 'reviewSeconds' => config('nfcheck.review_seconds'), 'duplicateSeconds' => config('nfcheck.duplicate_seconds')]);
    }

    public function index(Request $request): Response
    {
        $admin = $request->user()->isAdmin();

        return Inertia::render('operations/Activity', ['admin' => $admin, 'stations' => $admin ? Gate::withTrashed()->orderBy('name')->limit(500)->get(['id', 'code', 'name'])->toArray() : self::stations($request->user())]);
    }

    public function feed(Request $request, ?Gate $gate = null): JsonResponse
    {
        $user = User::findOrFail($request->user()->id);
        abort_unless($user->isActive() && $user->auth_version === $request->user()->auth_version, 403);
        $admin = $user->isAdmin();
        $validator = Validator::make($request->query(), ['gate_id' => ['nullable', 'integer', 'min:1']]);
        abort_if($validator->fails(), 422, 'Invalid activity filter.');
        $source = EnsureNfcSimulator::available($request) ? 'simulated' : 'physical';
        $query = ScanAttempt::with(['gate', 'guardUser', 'person', 'passage.latestDecision', 'duplicate'])->where('source', $source);
        if (! $admin) {
            abort_unless($user->isGuard(), 403);
            $query->where('received_at', '>=', now()->subMinutes(config('nfcheck.guard_activity_minutes')))
                ->where('gate_id', $user->gate_id ?? 0)->whereHas('gate', fn ($q) => $q->where('status', 'active'));
        }
        $gateId = $gate?->id ?? ($validator->validated()['gate_id'] ?? null);
        if ($gateId) {
            $query->where('gate_id', $gateId);
        }
        $records = $query->latest('received_at')->latest('id')->limit($admin ? 50 : 30)->get()->map(fn ($attempt) => ['id' => $attempt->public_id, 'person' => ($admin || $attempt->outcome === 'allowed') ? $attempt->person?->full_name : null, 'gate' => $attempt->gate->code.' · '.$attempt->gate->name, 'guard' => $attempt->guardUser->name, 'direction' => $attempt->direction, 'source' => $attempt->source, 'outcome' => $attempt->outcome, 'reason' => $attempt->reason, 'review_status' => $attempt->effectiveReviewStatus(), 'review_reason' => $attempt->review_reason, 'received_at' => $attempt->received_at->toIso8601String(), 'confirmed_at' => $attempt->passage?->confirmed_at->toIso8601String(), 'voided' => $attempt->passage?->latestDecision?->action === 'void', 'duplicate' => $attempt->duplicate_of_event_id !== null]);

        return response()->json(['records' => $records, 'server_time' => now()->toIso8601String(), 'minutes' => $admin ? null : config('nfcheck.guard_activity_minutes'), 'limit' => $admin ? 50 : 30]);
    }
}
