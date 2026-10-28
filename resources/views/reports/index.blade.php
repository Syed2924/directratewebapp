@extends('layouts.app')
@section('title', 'Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reports</h1>
            <p>Select a report to view, print or export. Every report shows only the data your account may access.</p>
        </div>
        <input type="search" class="form-control" style="max-width:280px" placeholder="Search reports…" id="reportSearch" aria-label="Search reports">
    </div>

    <div class="row g-3">
        @foreach (App\Support\Reports\ReportCatalog::CATEGORIES as $cat => [$label, $icon, $tint, $desc])
            <div class="col-md-6 col-xl-4" data-report-card>
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-center mb-3">
                            <span class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                            <div><div class="fw-semibold">{{ $label }}</div><div class="small text-muted-2">{{ $desc }}</div></div>
                        </div>
                        <ul class="list-unstyled mb-0">
                            @foreach (App\Support\Reports\ReportCatalog::REPORTS as $key => [$c, $title, $purpose])
                                @continue($c !== $cat)
                                <li class="py-1 border-top" data-report-item="{{ strtolower($title . ' ' . $purpose) }}">
                                    <a href="{{ route('reports.show', $key) }}" class="fw-medium">{{ $title }}</a>
                                    <div class="small-2 text-muted-2">{{ $purpose }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('reportSearch').addEventListener('input', function () {
            var q = this.value.toLowerCase();
            document.querySelectorAll('[data-report-item]').forEach(function (li) { li.hidden = q && li.dataset.reportItem.indexOf(q) === -1; });
            document.querySelectorAll('[data-report-card]').forEach(function (c) { c.hidden = !c.querySelector('[data-report-item]:not([hidden])'); });
        });
    </script>
@endpush
