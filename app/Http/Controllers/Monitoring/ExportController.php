<?php

namespace App\Http\Controllers\Monitoring;

use App\Services\AuditLogger;
use App\Services\Monitoring\MonitoringSource;
use App\Services\Monitoring\ReportExport;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExportController
{
    public function __invoke(Request $request, ReportTable $tables, ReportExport $export)
    {
        Gate::authorize('admin.workspace');
        $data = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])], 'dataset' => ['required', Rule::in(['summary', 'passages', 'attempts'])]]);
        $f = ReportFilters::request($request);
        $source = MonitoringSource::for($request);
        $limit = $data['format'] === 'pdf' ? 500 : 10000;
        $table = DB::transaction(fn () => $tables->build($f, $source, $data['dataset'], $limit));
        $content = $export->content($table, $f, $source, $data['dataset'], $data['format']);
        $audit = app(AuditLogger::class);
        $audit->record('report.exported', $request->user(), metadata: ['source' => $source, 'format' => $data['format'], 'dataset' => $data['dataset'], 'period_start' => $f->values['from'], 'period_end' => $f->values['to'], 'row_count' => count($table['rows']), 'filters_hash' => $audit->fingerprint(json_encode($f->values))]);
        $type = match ($data['format']) {
            'csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'
        };
        $name = "nfcheck-{$source}-{$data['dataset']}-{$f->values['from']}-{$f->values['to']}.{$data['format']}";

        return response($content, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'no-store, private']);
    }
}
