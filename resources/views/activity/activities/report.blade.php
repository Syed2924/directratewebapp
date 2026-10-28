<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Report {{ $proposal->tracking_no }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <style>
        body { background: #eef1f5; color: #1d2b3a; font-size: .88rem; }
        .sheet { background: #fff; max-width: 820px; margin: 1.5rem auto; padding: 2.2rem 2.4rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .head { display: flex; gap: 1rem; align-items: center; border-bottom: 2px solid #0b2a4a; padding-bottom: 1rem; margin-bottom: 1.2rem; }
        .head h1 { font-size: 1.15rem; margin: 0; color: #0b2a4a; }
        h2 { font-size: .95rem; color: #0b2a4a; border-bottom: 1px solid #e3e8ef; padding-bottom: .3rem; margin: 1.4rem 0 .7rem; }
        table.kv th { width: 34%; color: #6b7a8c; font-weight: 500; }
        table td, table th { padding: .35rem .5rem !important; }
        .pics { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
        .pics img { width: 100%; height: 140px; object-fit: cover; border: 1px solid #e3e8ef; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2.5rem; }
        .sign div { border-top: 1px solid #333; padding-top: .3rem; font-size: .8rem; }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="text-center mt-3 no-print">
    <button onclick="window.print()" class="btn btn-success btn-sm"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
</div>
<div class="sheet">
    <div class="head">
        @include('partials.logo', ['size' => 54])
        <div class="flex-grow-1">
            <h1>Directorate of Youth Affairs, Khyber Pakhtunkhwa</h1>
            <div class="text-muted">Activity Completion Report</div>
        </div>
        <div class="text-end small">
            <div class="fw-bold">{{ $proposal->tracking_no }}</div>
            <div>{{ $activity->statusLabel() }}</div>
            <div class="text-muted">Printed {{ now()->format('d M Y') }}</div>
        </div>
    </div>

    @if ($activity->status !== 'completed')
        <div class="alert alert-warning py-2 small">Draft — this activity has not been verified and finalized yet.</div>
    @endif

    <h2>1. Proposal</h2>
    <table class="table table-sm kv mb-0">
        <tr><th>Activity Title</th><td>{{ $proposal->title }}</td></tr>
        <tr><th>Category</th><td>{{ $proposal->categoryLabel() }}</td></tr>
        <tr><th>Applicant / Organization</th><td>{{ $proposal->applicant_name }}{{ $proposal->contact_person ? ' (' . $proposal->contact_person . ')' : '' }}</td></tr>
        <tr><th>District</th><td>{{ $activity->district->name }}</td></tr>
        <tr><th>Received / Approved</th><td>{{ $proposal->received_on->format('d M Y') }} / {{ $proposal->decided_on?->format('d M Y') ?? '—' }}</td></tr>
        <tr><th>Approved Budget</th><td>PKR {{ number_format($proposal->estimated_budget, 2) }}</td></tr>
    </table>

    <h2>2. Implementation</h2>
    <table class="table table-sm kv mb-0">
        <tr><th>Dates</th><td>{{ $activity->actual_start?->format('d M Y') ?? '—' }}{{ $activity->actual_end && ! $activity->actual_end->eq($activity->actual_start ?? $activity->actual_end) ? ' – ' . $activity->actual_end->format('d M Y') : '' }}</td></tr>
        <tr><th>Location</th><td>{{ $activity->location ?? '—' }}</td></tr>
        <tr><th>Participants</th><td>{{ $activity->participantsTotal() }} ({{ $activity->actual_male }} male, {{ $activity->actual_female }} female) — expected {{ $proposal->participantsTotal() }}</td></tr>
        <tr><th>Summary</th><td style="white-space:pre-line">{{ $activity->summary ?? '—' }}</td></tr>
        @if ($activity->remarks)<tr><th>Remarks</th><td style="white-space:pre-line">{{ $activity->remarks }}</td></tr>@endif
    </table>

    <h2>3. Expenses</h2>
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount (PKR)</th></tr></thead>
        <tbody>
        @forelse ($activity->expenses as $e)
            <tr><td class="text-nowrap">{{ $e->txn_date->format('d M Y') }}</td><td>{{ $e->categoryLabel() }}{{ $e->status === 'pending' ? ' (unverified)' : '' }}</td><td>{{ $e->description }}</td><td class="text-end">{{ number_format($e->amount, 2) }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">No expenses recorded.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">{{ number_format($activity->expenses->sum('amount'), 2) }}</td></tr>
        <tr><td colspan="3" class="text-end">Balance against approved budget</td><td class="text-end">{{ number_format($proposal->estimated_budget - $activity->expenses->sum('amount'), 2) }}</td></tr>
        </tfoot>
    </table>

    @php($pictures = $activity->documents->where('kind', 'picture')->filter->isImage()->take(6))
    @if ($pictures->isNotEmpty())
        <h2>4. Pictures</h2>
        <div class="pics">
            @foreach ($pictures as $pic)
                <img src="{{ route('activity.documents.show', [$pic, 'inline' => 1]) }}" alt="{{ $pic->original_name }}">
            @endforeach
        </div>
    @endif

    <h2>{{ $pictures->isNotEmpty() ? 5 : 4 }}. Approval &amp; Verification Trail</h2>
    <table class="table table-sm">
        @foreach ($proposal->actions as $step)
            <tr><td class="text-nowrap text-muted">{{ $step->created_at->format('d M Y') }}</td><td>{{ $step->label() }}</td><td>{{ $step->user->name ?? '' }}</td></tr>
        @endforeach
    </table>

    <div class="sign">
        <div>Submitted by: {{ $activity->completionSubmitter->name ?? '' }}</div>
        <div>Verified by: {{ $activity->verifier->name ?? '' }}{{ $activity->verified_at ? ' · ' . $activity->verified_at->format('d M Y') : '' }}</div>
    </div>
</div>
</body>
</html>
