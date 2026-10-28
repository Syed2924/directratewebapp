@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Access denied')

@section('content')
    <div class="text-center py-5">
        <div class="stat-icon tint-rose mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-shield-x"></i></div>
        <h1 class="h3">Access denied</h1>
        <p class="text-muted-2">{{ $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'You do not have permission to view this page or perform this action.' }}</p>
        <p class="small text-muted-2">This attempt has been recorded. If you need access, contact your administrator.</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary mt-2">Go to {{ auth()->check() ? 'Dashboard' : 'Sign in' }}</a>
    </div>
@endsection
