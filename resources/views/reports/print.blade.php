<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
    <style>
        body { background: #eef1f5; font-size: .82rem; }
        .sheet { background: #fff; max-width: 1100px; margin: 1.2rem auto; padding: 2rem 2.2rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .letterhead { display: flex; align-items: center; gap: 1rem; border-bottom: 2px solid #0b2a4a; padding-bottom: .9rem; margin-bottom: 1rem; }
        .letterhead h1 { font-size: 1.05rem; margin: 0; color: #0b2a4a; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 1.05rem; margin: 0; color: #0b2a4a; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); border: 1px solid #e3e8ef; margin-bottom: 1rem; }
        .summary-grid div { padding: .5rem .7rem; border-right: 1px solid #e3e8ef; }
        .summary-grid .k { color: #6b7a8c; font-size: .72rem; }
        .summary-grid .v { font-weight: 700; }
        .report-section { box-shadow: none; break-inside: auto; }
        .report-section .card-header { background: #f3f6f9; }
        .table td, .table th { padding: .3rem .45rem; }
        .foot { display: flex; justify-content: space-between; margin-top: 2rem; font-size: .75rem; color: #6b7a8c; border-top: 1px solid #e3e8ef; padding-top: .5rem; }
        @page { size: A4 landscape; margin: 12mm; @bottom-right { content: "Page " counter(page) " of " counter(pages); } }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; } .no-print { display: none !important; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
<div class="text-center mt-3 no-print">
    <button onclick="window.print()" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF / Print</button>
    <span class="small text-muted ms-2">In the print dialog choose “Save as PDF”.</span>
</div>
<div class="sheet">
    <div class="letterhead">
        @include('partials.logo', ['size' => 56])
        <div class="flex-grow-1">
            <h1>Directorate of Youth Affairs</h1>
            <div>Khyber Pakhtunkhwa</div>
        </div>
        <div class="doc-title">
            <h2>{{ $title }}</h2>
            <div>Reporting period: <strong>{{ $report['period'] ?? $f->period() }}</strong></div>
            @if ($f->districtName())<div>District: {{ $f->districtName() }}</div>@endif
            <div class="text-muted">Generated on {{ now()->format('d M Y, H:i') }}</div>
        </div>
    </div>

    @if ($report['summary'])
        <div class="summary-grid">
            @foreach ($report['summary'] as [$label, $value, $kind])
                <div><div class="k">{{ $label }}</div><div class="v">{{ match ($kind) { 'money' => 'PKR ' . number_format((float) $value), 'pct' => $value . '%', 'int' => number_format((float) $value), default => $value } }}</div></div>
            @endforeach
        </div>
    @endif

    @foreach ($report['sections'] as $i => $section)
        @include('reports.partials.section', ['section' => $section, 'interactive' => false, 'sid' => 'ps' . $i])
    @endforeach

    <div class="foot">
        <div>Prepared by: <strong>{{ auth()->user()->name }}</strong>, {{ auth()->user()->primaryRoleName() }}<br>Report Management System — figures limited to the preparer's access scope.</div>
        <div class="text-end">Signature: ____________________<br>Date: ____________________</div>
    </div>
</div>
</body>
</html>
