<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>

    <style>
        @page {
            margin: 34px 38px 42px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #18181b;
            background: #ffffff;
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        .header {
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 3px solid #b91c1c;
        }

        .brand {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1.4px;
            color: #b91c1c;
            text-transform: uppercase;
        }

        .title {
            margin: 8px 0 5px;
            font-size: 24px;
            line-height: 1.15;
            color: #09090b;
        }

        .muted {
            color: #71717a;
        }

        .section {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .section-title {
            margin-bottom: 8px;
            padding-bottom: 5px;
            font-size: 14px;
            border-bottom: 1px solid #d4d4d8;
            color: #18181b;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            width: 50%;
            padding: 5px 8px 5px 0;
            vertical-align: top;
        }

        .label {
            display: block;
            margin-bottom: 2px;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .5px;
            color: #71717a;
            text-transform: uppercase;
        }

        .value {
            color: #18181b;
            word-break: break-word;
        }

        .risk-box {
            padding: 12px;
            border: 1px solid #fecaca;
            background: #fff7f7;
        }

        .risk-score {
            font-size: 26px;
            font-weight: bold;
            color: #b91c1c;
        }

        .finding {
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid #d4d4d8;
            page-break-inside: avoid;
        }

        .finding-title {
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #d4d4d8;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .code {
            margin-top: 6px;
            padding: 7px;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8px;
            line-height: 1.4;
            color: #27272a;
            background: #f4f4f5;
            border: 1px solid #e4e4e7;
        }

        .footer-note {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px solid #d4d4d8;
            color: #71717a;
            font-size: 8px;
        }
    </style>
</head>

<body>
    @php
        $target = $report['target_snapshot'] ?? [];
        $assessment = $report['assessment_snapshot'] ?? [];
        $risk = $report['risk_snapshot'] ?? [];
        $findings = $report['findings_snapshot'] ?? [];
        $intelligence = $report['intelligence_snapshot'] ?? [];
        $metadata = $report['metadata'] ?? [];

        $display = static function ($value) {
            if ($value === null || $value === '') {
                return '—';
            }

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                return json_encode(
                    $value,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                );
            }

            return (string) $value;
        };
    @endphp

    <div class="header">
        <div class="brand">CrypticX Lab · Immutable Security Report</div>

        <h1 class="title">{{ $report['title'] }}</h1>

        <div class="muted">
            Report ID: {{ $report['id'] }}
            · Generated: {{ $display($report['generated_at']) }}
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Executive Risk Summary</h2>

        <div class="risk-box">
            <div class="risk-score">
                {{ isset($risk['score']) ? (int) $risk['score'] : 0 }}/100
            </div>

            <div>
                Risk level:
                <strong>{{ strtoupper($display($risk['level'] ?? null)) }}</strong>
            </div>

            <div class="muted" style="margin-top: 5px;">
                Risk is a prioritization score, not a probability of compromise.
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Target Snapshot</h2>

        <table class="grid">
            <tr>
                <td>
                    <span class="label">Name</span>
                    <span class="value">{{ $display($target['name'] ?? null) }}</span>
                </td>
                <td>
                    <span class="label">Hostname</span>
                    <span class="value">{{ $display($target['hostname'] ?? null) }}</span>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">URL</span>
                    <span class="value">{{ $display($target['url'] ?? null) }}</span>
                </td>
                <td>
                    <span class="label">Target ID</span>
                    <span class="value">{{ $report['target_id'] }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Assessment Snapshot</h2>

        <table class="grid">
            <tr>
                <td>
                    <span class="label">Assessment ID</span>
                    <span class="value">{{ $report['assessment_id'] }}</span>
                </td>
                <td>
                    <span class="label">Profile</span>
                    <span class="value">{{ $display($assessment['profile'] ?? null) }}</span>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Status</span>
                    <span class="value">{{ $display($assessment['status'] ?? null) }}</span>
                </td>
                <td>
                    <span class="label">Finding Count</span>
                    <span class="value">{{ count($findings) }}</span>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Started</span>
                    <span class="value">{{ $display($assessment['started_at'] ?? null) }}</span>
                </td>
                <td>
                    <span class="label">Completed</span>
                    <span class="value">{{ $display($assessment['completed_at'] ?? null) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">
            Findings Snapshot ({{ count($findings) }})
        </h2>

        @forelse ($findings as $index => $finding)
            <div class="finding">
                <div class="finding-title">
                    {{ $index + 1 }}.
                    {{ $display($finding['title'] ?? 'Untitled finding') }}
                </div>

                <div>
                    <span class="badge">
                        {{ $display($finding['severity'] ?? null) }}
                    </span>

                    @if (!empty($finding['confidence']))
                        <span class="badge">
                            Confidence: {{ $display($finding['confidence']) }}
                        </span>
                    @endif
                </div>

                @if (!empty($finding['description']))
                    <p style="margin-top: 8px;">
                        {{ $finding['description'] }}
                    </p>
                @endif

                @if (!empty($finding['evidence']))
                    <div>
                        <span class="label">Evidence</span>
                        <div class="code">{{ $display($finding['evidence']) }}</div>
                    </div>
                @endif

                @if (!empty($finding['structured_evidence']))
                    <div style="margin-top: 7px;">
                        <span class="label">Structured Evidence</span>
                        <div class="code">{{ $display($finding['structured_evidence']) }}</div>
                    </div>
                @endif

                @if (!empty($finding['remediation']))
                    <div style="margin-top: 7px;">
                        <span class="label">Remediation</span>
                        <div class="code">{{ $display($finding['remediation']) }}</div>
                    </div>
                @endif
            </div>
        @empty
            <p class="muted">No findings were stored in this report snapshot.</p>
        @endforelse
    </div>

    <div class="section">
        <h2 class="section-title">Assessment Intelligence Snapshot</h2>

        @if (!empty($intelligence))
            <div class="code">{{ $display($intelligence) }}</div>
        @else
            <p class="muted">
                No assessment intelligence snapshot was stored for this report.
            </p>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">Risk Provenance</h2>
        <div class="code">{{ $display($risk) }}</div>
    </div>

    <div class="section">
        <h2 class="section-title">Report Integrity Metadata</h2>
        <div class="code">{{ $display($metadata) }}</div>
    </div>

    <div class="footer-note">
        This document was rendered from the persisted CrypticX Lab report snapshot.
        Current target, finding lifecycle, or risk state is not recalculated during
        PDF generation.
    </div>
</body>
</html>
