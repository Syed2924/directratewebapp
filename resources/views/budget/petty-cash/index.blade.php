@extends('layouts.app')
@section('title', 'Petty Cash')

@section('content')
    <div class="page-head">
        <div>
            <h1>Petty Cash Management</h1>
            <p>Small cash funds for authorized minor expenses — separate from office budgets.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="{{ auth()->user()->can('create', App\Models\PettyCashFund::class) && $offices->isNotEmpty() ? 'col-xl-8' : 'col-12' }}">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Office</th><th>Custodian</th><th class="text-end">Opening</th><th class="text-end">Cash Balance</th><th>Last Reconciliation</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($funds as $f)
                            @php($bal = $f->balance())
                            @php($rec = $f->reconciliations->first())
                            <tr>
                                <td><a href="{{ route('budget.petty-cash.show', $f) }}" class="fw-medium">{{ $f->office->name }}</a><div class="small-2 text-muted-2">{{ $f->office->district->name }}</div></td>
                                <td>{{ $f->custodian->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($f->opening_balance) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($bal) }}</td>
                                <td class="small">@if ($rec){{ $rec->reconciled_on->format('d M Y') }} @include('budget.partials.status', ['status' => $rec->status])@else<span class="text-muted-2">Never</span>@endif</td>
                                <td class="text-end"><a href="{{ route('budget.petty-cash.show', $f) }}" class="btn btn-sm btn-light">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-2 py-5">No petty cash funds in your scope.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (auth()->user()->can('create', App\Models\PettyCashFund::class) && $offices->isNotEmpty())
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">Open a Petty Cash Fund</div>
                    <form method="POST" action="{{ route('budget.petty-cash.store') }}" class="card-body row g-3" novalidate>
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="office_id">Office</label>
                            <select id="office_id" name="office_id" class="form-select @error('office_id') is-invalid @enderror">
                                @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id') == $o->id)>{{ $o->name }}</option>@endforeach
                            </select>
                            @error('office_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="custodian_id">Person Responsible (Custodian)</label>
                            <select id="custodian_id" name="custodian_id" class="form-select">
                                @foreach ($custodians as $u)<option value="{{ $u->id }}" @selected(old('custodian_id') == $u->id)>{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="opening_balance">Opening Balance</label>
                            <input type="number" min="0" id="opening_balance" name="opening_balance" class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance', 20000) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="limit">Fund Limit</label>
                            <input type="number" min="0" id="limit" name="limit" class="form-control" value="{{ old('limit') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="opened_on">Opened On</label>
                            <input type="date" id="opened_on" name="opened_on" max="{{ now()->toDateString() }}" class="form-control" value="{{ old('opened_on', now()->toDateString()) }}">
                        </div>
                        <div class="col-12 d-grid"><button class="btn btn-primary">Open Fund</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
