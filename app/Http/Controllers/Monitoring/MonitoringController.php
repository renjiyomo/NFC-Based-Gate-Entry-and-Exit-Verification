<?php

namespace App\Http\Controllers\Monitoring;

use App\Models\AccessEvent;
use App\Models\AuditLog;
use App\Models\Gate;
use App\Models\ScanAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Monitoring\HistoryData;
use App\Services\Monitoring\MonitoringSource;
use App\Services\Monitoring\PassageCorrections;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate as Authorization;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController
{
    public function index(Request $request, ReportQueries $queries): Response
    {
        Authorization::authorize('admin.workspace');
        $page = $request->route('section', 'dashboard');
        $source = MonitoringSource::for($request);
        $f = ReportFilters::request($request);
        $data = DB::transaction(function () use ($request, $page, $source, $f, $queries): array {
            $props = ['section' => $page, 'filters' => $f->values, 'period' => $f->metadata(), 'source' => $source,
                'gates' => Gate::withTrashed()->orderBy('code')->limit(500)->get(['id', 'code', 'name'])->toArray(),
                'guards' => User::withTrashed()->where(fn ($q) => $q->where('role', 'guard')->orWhereIn('id', ScanAttempt::select('guard_id')))->orderBy('name')->limit(500)->get(['id', 'name'])->toArray()];
            if ($page === 'history') {
                $history = $queries->history($f, $source);
                $records = (clone $history)->paginate((int) $f->values['per_page']);
                if ($records->currentPage() > $records->lastPage()) {
                    $records = $history->paginate((int) $f->values['per_page'], page: $records->lastPage());
                }
                $props['filters']['page'] = $records->currentPage();
                $props['records'] = $records->appends($f->values)->through(fn ($r) => HistoryData::row($r, $f));
            } else {
                $props['summary'] = $queries->summary($f, $source);
                if ($page === 'dashboard') {
                    $props['currentPresence'] = $queries->presence(new ReportFilters(asOf: $f->asOf), $source);
                    $props['directory'] = $queries->directoryCounts($source);
                    $props['recent'] = $queries->attempts($f, $source)->with(['person', 'credential', 'gate', 'guardUser', 'passage.latestDecision', 'duplicate'])->latest('received_at')->latest('id')->limit(8)->get()->map(fn ($r) => HistoryData::row($r, $f))->all();
                }
                if ($page === 'reports') {
                    $props['exports'] = AuditLog::with('actor')->where('action', 'report.exported')->latest('id')->limit(10)->get()->map(fn ($a) => ['at' => $a->created_at->toIso8601String(), 'actor' => $a->actor?->name, 'details' => $a->metadata])->all();
                }
            }
            app(AuditLogger::class)->record('monitoring.viewed', $request->user(), metadata: ['reason' => $page, 'source' => $source]);

            return $props;
        });

        return Inertia::render('monitoring/Index', $data);
    }

    public function show(Request $request, string $view, string $id): Response
    {
        Authorization::authorize('admin.workspace');
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $source = MonitoringSource::for($request);
        $record = ($view === 'passages' ? AccessEvent::query()->with(['person', 'attempt.credential', 'gate', 'guardUser', 'latestDecision']) : ScanAttempt::query()->with(['person', 'credential', 'gate', 'guardUser', 'passage.latestDecision', 'duplicate']))->where('source', $source)->where('public_id', $id)->firstOrFail();
        $event = $record instanceof AccessEvent ? $record : $record->passage;
        $corrections = $event?->corrections()->with('actor')->reorder()->latest('id')->paginate(20)->through(fn ($c) => ['id' => $c->public_id, 'action' => $c->action, 'reason' => $c->reason, 'actor' => $c->actor->name, 'at' => $c->created_at->toIso8601String()]);
        app(AuditLogger::class)->record('monitoring.record_viewed', $request->user(), $record, ['source' => $source]);

        return Inertia::render('monitoring/Show', ['record' => HistoryData::row($record, new ReportFilters), 'corrections' => $corrections, 'revision' => (int) ($event?->corrections()->max('id') ?? 0), 'source' => $source]);
    }

    public function correct(Request $request, AccessEvent $event, PassageCorrections $service)
    {
        Authorization::authorize('admin.workspace');
        $data = $request->validate(['action' => ['required', Rule::in(['note', 'void', 'reinstate'])], 'reason' => ['required', 'string', 'min:5', 'max:1000', 'not_regex:/nfcheck:v[0-9]+:/i'], 'expected_revision' => ['required', 'integer', 'min:0']]);
        $service->append($request->user(), $event, $data, MonitoringSource::for($request));

        return redirect('/admin/history/passages/'.$event->public_id)->with('status', 'Correction appended. Original history retained.');
    }
}
