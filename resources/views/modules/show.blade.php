@extends('layouts.app')
@section('title', $label)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $label }}</h1>
            <p>This module is scheduled for a later phase. Access control for it is already enforced.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Your permissions in this module</div>
                <ul class="list-group list-group-flush">
                    @foreach ($actions as $action => $granted)
                        <li class="list-group-item d-flex justify-content-between">
                            {{ config("access.actions.$action") }}
                            @if ($granted)
                                <span class="text-primary"><i class="bi bi-check-circle-fill"></i> Allowed</span>
                            @else
                                <span class="text-muted-2"><i class="bi bi-dash-circle"></i> Not granted</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Your organizational scope</div>
                <div class="card-body">
                    <p class="mb-2"><strong>{{ $coverage->summary() }}</strong></p>
                    <p class="text-muted-2 small mb-0">Records in this module will be limited to these districts, offices, centers and hostels, in addition to your role permissions.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
