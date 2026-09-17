<?php

namespace App\Services;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportPdfService
{
    public function download(Report $report): Response
    {
        /*
         * IMPORTANT:
         * PDF generation intentionally uses persisted report snapshots only.
         * It must never recalculate risk or read current finding lifecycle state.
         */
        $payload = [
            'id' => (string) $report->id,
            'assessment_id' => (string) $report->assessment_id,
            'target_id' => (string) $report->target_id,
            'title' => (string) $report->title,
            'status' => (string) $report->status,

            'target_snapshot' => is_array($report->target_snapshot)
                ? $report->target_snapshot
                : [],

            'assessment_snapshot' => is_array($report->assessment_snapshot)
                ? $report->assessment_snapshot
                : [],

            'findings_snapshot' => is_array($report->findings_snapshot)
                ? $report->findings_snapshot
                : [],

            'risk_snapshot' => is_array($report->risk_snapshot)
                ? $report->risk_snapshot
                : [],

            'intelligence_snapshot' => is_array($report->intelligence_snapshot)
                ? $report->intelligence_snapshot
                : [],

            'metadata' => is_array($report->metadata)
                ? $report->metadata
                : [],

            'generated_at' => $report->generated_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
        ];

        $baseName = Str::slug($report->title);

        if ($baseName === '') {
            $baseName = 'crypticx-security-report';
        }

        $filename = sprintf(
            '%s-%s.pdf',
            Str::limit($baseName, 80, ''),
            substr((string) $report->id, 0, 8)
        );

        $logoPath = dirname(base_path()).'/public/brand/crypticx2.png';

        $logoDataUri = null;

        if (is_file($logoPath) && is_readable($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode(
                file_get_contents($logoPath)
            );
        }

        return Pdf::loadView('reports.pdf', [
            'report' => $payload,
            'logoDataUri' => $logoDataUri,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
