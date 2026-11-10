<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\Monitoring\ReportExport;
use App\Services\Monitoring\ReportFilters;
use App\Services\Monitoring\ReportQueries;
use App\Services\Monitoring\ReportTable;
use Illuminate\Validation\ValidationException;
use Tests\MonitoringTestCase;

class ReportExportTest extends MonitoringTestCase
{
    public function test_csv_is_private_audited_reconciled_and_neutralizes_formula_cells(): void
    {
        $p = $this->subject();
        $p->update(['full_name' => ' =HYPERLINK("https://example.invalid","text")']);
        $this->passage($p, '2026-11-09 01:00:00');
        $this->finishTime();
        $response = $this->get('/admin/reports/export?date=2026-11-09&format=csv&dataset=passages');
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = explode("\r\n", trim(substr($content, 3)));
        $header = str_getcsv($lines[0], ',', '"', '');
        $row = str_getcsv($lines[1], ',', '"', '');
        $this->assertCount(2, $lines);
        $this->assertCount(count($header), $row);
        $this->assertStringStartsWith("' =HYPERLINK", $row[array_search('Person', $header)]);
        $this->assertSame('2026-11-09 09:00:00.000000', $row[array_search('Time (Asia/Manila)', $header)]);
        $this->assertStringNotContainsString($this->receipts[$p->id]['payload'], $content);
        $audit = AuditLog::where('action', 'report.exported')->sole();
        $this->assertSame(1, $audit->metadata['row_count']);
        $this->assertArrayHasKey('filters_hash', $audit->metadata);
        $this->assertArrayNotHasKey('q', $audit->metadata);
    }

    public function test_excel_uses_literal_strings_instead_of_formulas_and_has_context(): void
    {
        $p = $this->subject();
        $p->update(['full_name' => '=1+1']);
        $this->passage($p, '2026-11-09 01:00:00');
        $this->finishTime();
        $response = $this->get('/admin/reports/export?date=2026-11-09&format=xlsx&dataset=passages')->assertOk();
        $this->assertStringStartsWith('PK', $response->getContent());
        $path = tempnam(storage_path('framework/testing'), 'xlsx-');
        file_put_contents($path, $response->getContent());
        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertStringContainsString('=1+1', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('inlineStr', $sheet);
            $this->assertStringContainsString('Asia/Manila', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertStringContainsString('Report context', $zip->getFromName('xl/workbook.xml'));
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function test_summary_pdf_renders_and_all_formats_use_the_same_source_table(): void
    {
        $p = $this->subject('employees');
        $this->passage($p, '2026-11-09 01:00:00');
        $this->finishTime();
        $f = new ReportFilters(['date' => '2026-11-09']);
        $summary = app(ReportQueries::class)->summary($f, 'physical');
        $table = app(ReportTable::class)->build($f, 'physical', 'summary', 500);
        $this->assertSame($summary['passages'], $table['rows'][0][4]);
        $response = $this->get('/admin/reports/export?date=2026-11-09&format=pdf&dataset=summary')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(8000, strlen($response->getContent()));
        // Ignored synthetic artifact for visual export QA, not a production export.
        file_put_contents(base_path('.tools/artifacts/synthetic-summary.pdf'), $response->getContent());
        $details = $this->get('/admin/reports/export?date=2026-11-09&format=pdf&dataset=passages')->assertOk();
        file_put_contents(base_path('.tools/artifacts/synthetic-details.pdf'), $details->getContent());
    }

    public function test_limits_validation_and_secret_flash_protection(): void
    {
        $p = $this->subject();
        $this->passage($p, '2026-11-09 01:00:00');
        $this->passage($p, '2026-11-09 02:00:00', 'exit');
        $this->finishTime();
        try {
            app(ReportTable::class)->build(new ReportFilters(['date' => '2026-11-09']), 'physical', 'passages', 1);
            $this->fail('Export silently exceeded its cap');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('export', $e->errors());
        }
        $this->getJson('/admin/reports/export?format=php&dataset=summary')->assertUnprocessable();
        $this->getJson('/admin/reports/export?format=csv&dataset=credentials')->assertUnprocessable();
        $this->get('/admin/history?q='.urlencode($this->receipts[$p->id]['payload']))->assertRedirect()->assertSessionMissing('_old_input.q');
        foreach (['=1+1', "\t+SUM(A1:A2)", '@CMD', '-2+1', "\r\n =2"] as $value) {
            $this->assertStringStartsWith("'", ReportExport::safeCsv($value));
        }
        $this->assertSame('ordinary name', ReportExport::safeCsv('ordinary name'));
    }
}
