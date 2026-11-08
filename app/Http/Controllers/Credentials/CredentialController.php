<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Requests\CredentialRequest;
use App\Models\NfcCredential;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Services\Credentials\CredentialData;
use App\Services\Credentials\CredentialDestination;
use App\Services\Credentials\CredentialLifecycle;
use App\Services\Credentials\CredentialVerifier;
use App\Support\DirectoryData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CredentialController
{
    public function destination(Request $request, CredentialDestination $destination, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('viewAny', NfcCredential::class);
        $data = $request->validate(['kind' => ['required', Rule::in(['empty', 'reference', 'other'])], 'payload' => ['nullable', 'required_if:kind,reference', 'string', 'max:256']]);
        $source = $request->routeIs('simulator.*') ? 'simulated' : 'physical';
        abort_if($source === 'physical' && config('database.connections.mysql.database') === 'nfcheck_simulator', 403);
        $result = $destination->inspect($data['kind'], $data['payload'] ?? null, $source);
        $audit->record('credential.destination_checked', $request->user(), metadata: ['reason' => $result['reason'], 'source' => $source]);

        return response()->json($result);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NfcCredential::class);
        $validator = Validator::make($request->query(), ['q' => ['nullable', 'string', 'max:100', 'not_regex:/nfcheck:v[0-9]+:/i'], 'status' => ['nullable', Rule::in(['current', 'all', 'pending', 'active', 'revoked', 'cancelled', 'expired'])], 'source' => ['nullable', Rule::in(['physical', 'simulated'])], 'per_page' => ['nullable', Rule::in([15, 30, 50])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        abort_if($validator->fails(), 422, 'Invalid credential filters.');
        $filters = $validator->validated();
        $filters['status'] = $filters['status'] ?? 'current';
        $query = NfcCredential::with(['assignment.person', 'replaces']);
        if (! empty($filters['q'])) {
            $like = DirectoryData::like($filters['q']);
            $query->where(fn ($q) => $q->whereRaw("label LIKE ? ESCAPE '!'", [$like])->orWhereHas('assignment.person', fn ($p) => $p->whereRaw("full_name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("campus_number LIKE ? ESCAPE '!'", [$like])));
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        $status = $filters['status'] ?? '';
        if ($status === 'current') {
            $query->where(fn ($q) => $q->where(fn ($pending) => $pending->where('status', 'pending')->where('pending_expires_at', '>', now()))
                ->orWhere(fn ($active) => $active->where('status', 'active')->where(fn ($valid) => $valid->whereNull('valid_until')->orWhere('valid_until', '>', now()))));
        } elseif ($status === 'expired') {
            $query->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($p) => $p->where('status', 'pending')->where('pending_expires_at', '<=', now()))->orWhere(fn ($p) => $p->where('status', 'active')->where('valid_until', '<=', now())));
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
            if ($status === 'pending') {
                $query->where('pending_expires_at', '>', now());
            }
            if ($status === 'active') {
                $query->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()));
            }
        }

        return Inertia::render('credentials/Index', ['filters' => ['q' => '', 'status' => '', 'source' => '', 'per_page' => 15, ...array_filter($filters, fn ($value) => $value !== null)], 'records' => $query->latest('id')->paginate((int) ($filters['per_page'] ?? 15))->appends($filters)->through(fn ($credential) => CredentialData::one($credential))]);
    }

    public function create(Request $request): Response
    {
        $data = $request->validate(['person_id' => ['nullable', 'integer', 'min:1'], 'replaces_id' => ['nullable', 'uuid'], 'mode' => ['nullable', Rule::in(['new', 'replace', 'lost'])]]);
        $person = isset($data['person_id']) ? Person::where('status', 'active')->findOrFail($data['person_id']) : null;

        return Inertia::render('credentials/Issue', ['selectedPerson' => $person ? $this->person($person) : null, 'mode' => $data['mode'] ?? 'new', 'replacesId' => $data['replaces_id'] ?? null, 'simulated' => $request->routeIs('simulator.*')]);
    }

    public function show(NfcCredential $credential): Response
    {
        Gate::authorize('view', $credential);

        return Inertia::render('credentials/Show', ['record' => CredentialData::one($credential->load(['assignment.person', 'replaces']))]);
    }

    public function people(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = Person::where('status', 'active');
        if (! empty($data['q'])) {
            $like = DirectoryData::like($data['q']);
            $query->where(fn ($q) => $q->whereRaw("full_name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("campus_number LIKE ? ESCAPE '!'", [$like]));
        }
        $results = $query->with('activeCredentialAssignment.credential')->orderBy('full_name')->orderBy('id')->paginate(20);

        return response()->json(['data' => $results->getCollection()->map(fn ($person) => $this->person($person)), 'has_more' => $results->hasMorePages()]);
    }

    private function person(Person $person): array
    {
        $current = $person->activeCredentialAssignment?->credential;
        if ($current?->effectiveStatus() !== 'active') {
            $current = null;
        }

        return ['id' => $person->id, 'name' => $person->full_name, 'number' => $person->campus_number, 'kind' => $person->person_type->value, 'record_version' => (int) $person->record_version, 'current' => $current ? ['id' => $current->public_id, 'label' => $current->label, 'source' => $current->source, 'status' => $current->effectiveStatus()] : null];
    }

    public function issue(CredentialRequest $request, CredentialLifecycle $lifecycle): JsonResponse
    {
        $source = $request->routeIs('simulator.*') ? 'simulated' : 'physical';
        abort_if($source === 'physical' && config('database.connections.mysql.database') === 'nfcheck_simulator', 403);
        try {
            $receipt = $lifecycle->issue($request->user(), $request->validated(), $request->session()->getId(), $source);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['label' => 'The label or issuance request was already used. Review the registry; secrets are delivered only once.']);
        }

        return response()->json($receipt, 201);
    }

    public function readBack(CredentialRequest $request, NfcCredential $credential, CredentialLifecycle $lifecycle): JsonResponse
    {
        Gate::authorize('manage', $credential);
        $record = $lifecycle->readBack($request->user(), $credential, $request->session()->getId(), $request->validated('payload'), $request->validated('completion_nonce'), $request->routeIs('simulator.*') ? 'simulated' : 'physical');

        return response()->json(['credential' => CredentialData::one($record->load('assignment.person'))]);
    }

    public function activate(CredentialRequest $request, NfcCredential $credential, CredentialLifecycle $lifecycle): JsonResponse
    {
        Gate::authorize('manage', $credential);
        $record = $lifecycle->activate($request->user(), $credential, $request->session()->getId(), $request->validated('payload'), $request->validated('completion_nonce'), $request->routeIs('simulator.*') ? 'simulated' : 'physical');

        return response()->json(['credential' => CredentialData::one($record->load('assignment.person'))]);
    }

    public function cancel(CredentialRequest $request, NfcCredential $credential, CredentialLifecycle $lifecycle): JsonResponse
    {
        Gate::authorize('manage', $credential);

        return response()->json(['credential' => CredentialData::one($lifecycle->terminate($request->user(), $credential, 'cancel', 'issuance_cancelled')->load('assignment.person'))]);
    }

    public function revoke(CredentialRequest $request, NfcCredential $credential, CredentialLifecycle $lifecycle): JsonResponse
    {
        Gate::authorize('manage', $credential);

        return response()->json(['credential' => CredentialData::one($lifecycle->terminate($request->user(), $credential, 'revoke', $request->validated('reason'))->load('assignment.person'))]);
    }

    public function check(CredentialRequest $request, CredentialVerifier $verifier, AuditLogger $audit): JsonResponse
    {
        $simulated = $request->routeIs('simulator.*');
        $result = $verifier->inspect($request->validated('payload'), $simulated);
        $audit->record('credential.inspected', $request->user(), metadata: ['reason' => $result['reason'], 'source' => $simulated ? 'simulated' : 'physical'], outcome: $result['eligible'] ? 'success' : 'denied');

        return response()->json([...$result, 'simulated' => $simulated, 'passage_recorded' => false]);
    }

    public function simulator(): Response
    {
        return Inertia::render('credentials/Simulator');
    }
}
