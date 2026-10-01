@extends('layouts.app')

@section('content')
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="admin-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <span class="hero-badge">First-time system setup</span>
                    <h1 class="fw-bold mt-3 mb-2">Create Primary Administrator</h1>
                    <p class="text-muted mb-0">Set up the administrator account that will manage the 3YOS Catering Management System. Use an email controlled by the beneficiary and keep the password private. Setup cannot be repeated once a Primary Administrator exists.</p>
                </div>

                @if(session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif

                @if($setupComplete)
                    <div class="alert alert-info" role="status">Primary Administrator setup has already been completed.</div>
                    <a href="{{ route('admin.login') }}" class="btn btn-primary w-100 py-2">Go to Admin Login</a>
                @elseif(!$setupAvailable)
                    <div class="alert alert-info" role="status">Primary Administrator setup is not available. Contact the system administrator.</div>
                    <a href="{{ route('admin.login') }}" class="btn btn-primary w-100 py-2">Go to Admin Login</a>
                @else
                    @if($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('admin.setup.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="setup-key">One-time Setup Key</label>
                            <input id="setup-key" name="setup_key" type="password" class="form-control @error('setup_key') is-invalid @enderror" autocomplete="off" required>
                            @error('setup_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="setup-name">Full Name</label>
                            <input id="setup-name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="setup-email">Email Address</label>
                            <input id="setup-email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="setup-password">Password</label>
                            <input id="setup-password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                            <div class="form-text">Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.</div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="setup-password-confirmation">Confirm Password</label>
                            <input id="setup-password-confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Create Primary Administrator</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
