<?php

namespace App\Http\Controllers\Anomalies;

use App\Models\AnomalyFlag;
use App\Services\Anomalies\Detection;
use App\Services\Anomalies\Review;
use App\Services\Anomalies\Rules;
use App\Services\AuditLogger;
use App\Services\Monitoring\HistoryData;
use App\Services\Monitoring\MonitoringSource;
use App\Services\Monitoring\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AnomalyController
{
    public function index(Request $r)
    {
        Gate::authorize('admin.workspace');
        $source = MonitoringSource::for($r);
        $f = ReportFilters::request($r);
        $extra = $r->validate(['status' => ['nullable', Rule::in(['open', 'in_review', 'resolved', 'false_alarm'])], 'rule' => ['nullable', Rule::in(array_keys(Rules::TITLES))], 'severity' => ['nullable', Rule::in(['low', 'medium'])]]);
        $q = AnomalyFlag::where('source', $source)->where('occurred_at', '>=', $f->from->format('Y-m-d H:i:s.u'))->where('occurred_at', '<', $f->until->format('Y-m-d H:i:s.u'))->where('occurred_at', '<=', $f->asOf->format('Y-m-d H:i:s.u'));
        if ($extra['status'] ?? null) {
            $q->where(function ($q) use ($extra): void {
                $q->whereHas('latestReview', fn ($v) => $v->where('status', $extra['status']));
                if ($extra['status'] === 'open') {
                    $q->orWhereDoesntHave('latestReview');
                }
            });
        }
        if ($extra['rule'] ?? null) {
            $q->where('rule_code', $extra['rule']);
        }
        if ($extra['severity'] ?? null) {
            $q->where('severity', $extra['severity']);
        }
        $query = $q->with(['latestReview.actor', 'anchor.person', 'anchor.gate'])->orderByDesc('occurred_at')->orderByDesc('id');
        $flags = (clone $query)->paginate(25);
        if ($flags->currentPage() > $flags->lastPage()) {
            $flags = $query->paginate(25, page: $flags->lastPage());
        }
        $flags->withQueryString()->through(fn ($a) => $this->row($a));
        $open = AnomalyFlag::where('source', $source)->where(fn ($q) => $q->whereDoesntHave('latestReview')->orWhereHas('latestReview', fn ($v) => $v->whereIn('status', ['open', 'in_review'])))->count();
        app(AuditLogger::class)->record('anomaly.viewed', $r->user(), metadata: ['source' => $source]);

        $codes = ['missing_exit', 'rapid_scans', 'revoked_use', 'unavailable_credential', 'denied_burst'];
        $codes = array_unique([...$codes, ...AnomalyFlag::where('source', $source)->distinct()->pluck('rule_code')->all()]);

        return Inertia::render('anomalies/Index', ['flags' => $flags, 'filters' => [...$f->values, ...$extra], 'period' => $f->metadata(), 'source' => $source, 'openCount' => $open, 'rules' => array_intersect_key(Rules::TITLES, array_flip($codes)), 'policy' => app(Rules::class)->policy()]);
    }

    public function detect(Request $r, Detection $detector)
    {
        Gate::authorize('admin.workspace');
        $data = $r->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'source' => ['prohibited']]);
        $f = new ReportFilters(['period' => 'custom', ...$data]);
        $result = $detector->run($f, MonitoringSource::for($r), $r->user());

        return redirect('/admin/anomalies?period=custom&from='.$data['from'].'&to='.$data['to'])->with('status', "Checked {$result['checked']} attempts; {$result['created']} new review flags. Existing flags were retained.");
    }

    public function show(Request $r, AnomalyFlag $anomaly, Detection $detector)
    {
        Gate::authorize('admin.workspace');
        abort_unless($anomaly->source === MonitoringSource::for($r), 404);
        $anomaly->load(['anchor.person', 'anchor.gate', 'latestReview.actor']);
        $f = new ReportFilters;
        $evidence = $anomaly->evidence()->with(['person', 'credential', 'gate', 'guardUser', 'passage.latestDecision', 'duplicate'])->orderBy('received_at')->orderBy('scan_attempts.id')->get()->map(fn ($a) => HistoryData::row($a, $f));
        app(AuditLogger::class)->record('anomaly.details_viewed', $r->user(), $anomaly, ['source' => $anomaly->source]);

        return Inertia::render('anomalies/Show', ['flag' => [...$this->row($anomaly), 'explanation' => $anomaly->explanation, 'policy' => $anomaly->policy, 'version' => $anomaly->rule_version, 'context' => $detector->context($anomaly)], 'evidence' => $evidence,
            'revision' => $anomaly->latestReview?->id ?? 0,
            'reviews' => $anomaly->reviews()->with('actor')->paginate(20)->through(fn ($v) => ['status' => $v->status, 'reason' => $v->reason, 'at' => $v->created_at->toIso8601String(), 'actor' => $v->actor->name])]);
    }

    public function review(Request $r, AnomalyFlag $anomaly, Review $service)
    {
        Gate::authorize('admin.workspace');
        $data = $r->validate(['status' => ['required', Rule::in(['open', 'in_review', 'resolved', 'false_alarm'])], 'reason' => ['required', 'string', 'min:5', 'max:1000', 'not_regex:/nfcheck:v[0-9]+:/i'], 'expected_revision' => ['required', 'integer', 'min:0'], 'source' => ['prohibited']]);
        $service->append($r->user(), $anomaly, MonitoringSource::for($r), $data);

        return redirect('/admin/anomalies/'.$anomaly->public_id)->with('status', 'Review decision appended. Original evidence retained.');
    }

    private function row(AnomalyFlag $a): array
    {
        return ['id' => $a->public_id, 'rule' => $a->rule_code, 'title' => Rules::TITLES[$a->rule_code], 'severity' => $a->severity, 'source' => $a->source, 'at' => $a->occurred_at->toIso8601String(), 'status' => $a->latestReview?->status ?? 'open', 'reviewer' => $a->latestReview?->actor?->name, 'person' => $a->rule_code === 'denied_burst' ? null : $a->anchor->person?->full_name, 'gate' => $a->anchor->gate?->code];
    }
}
