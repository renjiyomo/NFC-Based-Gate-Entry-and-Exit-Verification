<?php

namespace App\Http\Controllers\Summaries;

use App\Models\SummaryRequest;
use App\Services\AuditLogger;
use App\Services\Monitoring\MonitoringSource;
use App\Services\Monitoring\ReportFilters;
use App\Services\Summaries\Aggregates;
use App\Services\Summaries\Generation;
use App\Services\Summaries\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SummaryController
{
    private function filters(Request $r): ReportFilters
    {
        $v = $r->validate(['period' => ['nullable', Rule::in(['daily', 'weekly'])], 'date' => ['nullable', 'date_format:Y-m-d'], 'source' => ['prohibited'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return new ReportFilters(array_intersect_key($v, array_flip(['period', 'date'])));
    }

    public function index(Request $r, Aggregates $aggregates, Generation $generation)
    {
        Gate::authorize('admin.workspace');
        $f = $this->filters($r);
        $source = MonitoringSource::for($r);
        $data = DB::transaction(function () use ($r, $f, $source, $aggregates, $generation): array {
            $q = SummaryRequest::where('source', $source)->with(['result', 'latestReview', 'task', 'actor'])->orderByDesc('id');
            $history = (clone $q)->paginate(20);
            if ($history->currentPage() > $history->lastPage()) {
                $history = $q->paginate(20, page: $history->lastPage());
            }
            $history->withQueryString()->through(fn ($v) => $this->row($v));
            app(AuditLogger::class)->record('summary.history_viewed', $r->user(), metadata: ['source' => $source]);

            return ['filters' => ['period' => $f->values['period'], 'date' => $f->values['date']], 'period' => $f->metadata(), 'source' => $source, 'facts' => $aggregates->build($f, $source), 'availability' => $generation->availability(), 'requestKey' => (string) Str::uuid(), 'history' => $history];
        });

        return Inertia::render('summaries/Index', $data);
    }

    public function store(Request $r, Generation $generation)
    {
        Gate::authorize('admin.workspace');
        $f = $this->filters($r);
        $v = $r->validate(['request_key' => ['required', 'uuid'], 'provider' => ['prohibited'], 'model' => ['prohibited'], 'api_key' => ['prohibited'], 'prompt' => ['prohibited']]);
        $request = $generation->request($r->user(), $f, MonitoringSource::for($r), $v['request_key']);
        if (config('ai_summaries.inline_generation')) {
            $generation->process($request->task);
        }

        return redirect('/admin/summaries/'.$request->public_id)->with('status', 'Summary request saved.');
    }

    public function show(Request $r, SummaryRequest $summary, Aggregates $aggregates)
    {
        Gate::authorize('admin.workspace');
        abort_unless($summary->source === MonitoringSource::for($r), 404);
        $r->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return DB::transaction(function () use ($r, $summary, $aggregates) {
            $summary->load(['result', 'latestReview', 'task', 'actor']);
            $current = $aggregates->build(new ReportFilters(['period' => $summary->period, 'date' => $summary->period_from]), $summary->source);
            app(AuditLogger::class)->record('summary.details_viewed', $r->user(), $summary, ['source' => $summary->source]);

            return Inertia::render('summaries/Show', ['summary' => [...$this->row($summary), 'snapshot' => $summary->snapshot, 'hash' => $summary->snapshot_hash, 'contract' => $summary->contract_version, 'changed' => $aggregates->hash($current) !== $summary->snapshot_hash, 'sentences' => $summary->result?->sentences, 'selectedFacts' => $summary->result?->selected_facts ?? [], 'error' => $summary->result?->error_code, 'provider' => $summary->result?->provider, 'model' => $summary->result?->model, 'tokens' => ['input' => $summary->result?->input_tokens, 'output' => $summary->result?->output_tokens], 'recoverable' => $summary->task?->claimed_at?->lessThanOrEqualTo(now()->subMinutes(2)) && ! $summary->result],
                'revision' => $summary->latestReview?->id ?? 0, 'reviews' => $summary->reviews()->with('actor')->paginate(20)->through(fn ($v) => ['decision' => $v->decision, 'reason' => $v->reason, 'at' => $v->created_at->toIso8601String(), 'actor' => $v->actor->name])]);
        });
    }

    public function review(Request $r, SummaryRequest $summary, Review $service)
    {
        Gate::authorize('admin.workspace');
        $v = $r->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['required', 'string', 'min:5', 'max:1000', 'not_regex:/nfcheck:v[0-9]+:/i'], 'expected_revision' => ['required', 'integer', 'min:0'], 'source' => ['prohibited']]);
        $service->append($r->user(), $summary, MonitoringSource::for($r), $v);

        return redirect('/admin/summaries/'.$summary->public_id)->with('status', 'Human review appended; previous decisions are retained.');
    }

    public function recover(Request $r, SummaryRequest $summary, Generation $generation)
    {
        Gate::authorize('admin.workspace');
        $generation->recover($r->user(), $summary, MonitoringSource::for($r));

        return redirect('/admin/summaries/'.$summary->public_id)->with('status', 'Request closed without another provider call.');
    }

    private function row(SummaryRequest $r): array
    {
        return ['id' => $r->public_id, 'from' => $r->period_from, 'to' => $r->period_to, 'period' => $r->period, 'source' => $r->source, 'at' => $r->snapshot_at->toIso8601String(), 'actor' => $r->actor->name, 'status' => $r->result?->status ?? ($r->task?->claimed_at ? 'processing' : 'queued'), 'review' => $r->latestReview?->decision ?? 'unreviewed', 'completed_at' => $r->result?->created_at?->toIso8601String()];
    }
}
