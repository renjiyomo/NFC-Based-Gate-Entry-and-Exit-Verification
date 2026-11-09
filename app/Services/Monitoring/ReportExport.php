<?php

namespace App\Services\Monitoring;

use Dompdf\Dompdf;
use Dompdf\Options as PdfOptions;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ReportExport
{
    public static function safeCsv(string $value): string
    {
        return preg_match('/^[\s\x{FEFF}]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    public function label(string $source): string
    {
        return match (config('database.connections.mysql.database')) {
            'nfcheck_hardware' => 'HARDWARE LAB - SYNTHETIC TEST RECORDS',
            'nfcheck_simulator', 'nfcheck_testing' => 'ISOLATED TEST WORKSPACE - '.$source,
            default => 'NFCheck Bicol University Polangui gate records - '.$source,
        };
    }

    public function content(array $table, ReportFilters $f, string $source, string $dataset, string $format): string
    {
        $label = $this->label($source);
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Workspace', 'Source', 'From (Manila)', 'Through (Manila)', 'Snapshot (UTC)', 'Applied filters', ...$table['headers']], ',', '"', '', "\r\n");
            foreach ($table['rows'] as $row) {
                fputcsv($stream, array_map(fn ($v) => self::safeCsv((string) $v), [$label, $source, $f->values['from'], $f->values['to'], $f->asOf->toIso8601String(), json_encode(array_intersect_key($f->values, array_flip(['q', 'kind', 'gate_id', 'guard_id', 'direction', 'outcome', 'review', 'validity'])), JSON_UNESCAPED_UNICODE), ...$row]), ',', '"', '', "\r\n");
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);

            return $content;
        }
        if ($format === 'pdf') {
            if ($dataset !== 'summary') {
                $table = ['headers' => ['Time (Manila)', 'Person / university number', 'Gate / guard', 'Direction', 'Verification / reason', 'Review / reason', 'Passage state', 'Credential / sequence'],
                    'rows' => array_map(fn ($r) => [$r[0], $r[1]."\n".$r[2]."\n".$r[3], $r[4]."\n".$r[5], $r[6], $r[7]."\n".$r[8], $r[9]."\n".$r[10], $r[11], $r[12]."\n".$r[13]], $table['rows'])];
            }
            $options = new PdfOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => [resource_path('views/reports'), base_path('vendor/dompdf/dompdf/lib/fonts')]]);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('reports.activity', compact('table', 'f', 'source', 'dataset', 'label'))->render(), 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            $pdf->getCanvas()->page_text(35, 570, 'NFCheck · Private report    |    Page {PAGE_NUM} of {PAGE_COUNT}', 'DejaVu Sans', 8, [0.3, 0.35, 0.42]);

            return $pdf->output();
        }
        $folder = storage_path('app/private/report-temp');
        if (! is_dir($folder)) {
            mkdir($folder, 0700, true);
        }
        $path = tempnam($folder, 'nfcheck-');
        $writer = new Writer(new Options(DEFAULT_COLUMN_WIDTH: 24, tempFolder: $folder));
        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Report context');
            $bold = new Style(fontBold: true, backgroundColor: 'DCE7F7');
            foreach ([['NFCheck', $label], ['Dataset', $dataset], ['Source', $source], ['Timezone', 'Asia/Manila'], ['From', $f->values['from']], ['Through', $f->values['to']], ['Snapshot UTC', $f->asOf->toIso8601String()], ['Filters', json_encode(array_intersect_key($f->values, array_flip(['q', 'kind', 'gate_id', 'guard_id', 'direction', 'outcome', 'review', 'validity'])), JSON_UNESCAPED_UNICODE)], ['Meaning', 'Effective confirmed gate passages; not classroom attendance. Presence is an estimate.']] as $r) {
                $writer->addRow(new Row(array_map(fn ($v) => new StringCell((string) $v), $r)));
            }
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(ucfirst($dataset));
            $sheet->setSheetView(new SheetView(freezeRow: 2));
            $writer->addRow(new Row(array_map(fn ($h) => new StringCell($h, $bold), $table['headers'])));
            foreach ($table['rows'] as $r) {
                $writer->addRow(new Row(array_map(fn ($v) => is_int($v) || is_float($v) ? new NumericCell($v) : new StringCell((string) $v), $r)));
            }
            $writer->close();

            return file_get_contents($path);
        } finally {
            $writer->close();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
