@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <h1 class="fw-bold mb-1">Admin Accounts</h1>
        <p class="text-muted mb-0">Create either a primary admin or a team admin and choose the access level for each account.</p>
    </div></div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-lg-12 col-xl-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3">Add admin</h5>
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Access role</label>
                        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                            <option value="full" {{ old('role') === 'full' ? 'selected' : '' }}>Primary admin</option>
                            <option value="limited" {{ old('role') === 'limited' ? 'selected' : '' }}>Team admin</option>
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                        <div class="form-text">At least 8 characters.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirm password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                    </div>
                    <button class="btn luxury-btn w-100" type="submit">Create admin</button>
                </form>
            </div>
        </div>
        <div class="col-lg-12 col-xl-8">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3">Created admins</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 team-admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge-soft {{ $user->role === 'full' ? 'badge-primary' : 'badge-team' }}">{{ $user->role === 'full' ? 'Primary admin' : 'Team admin' }}</span>
                                    </td>
                                    <td>
                                        <div class="team-admin-access">
                                            <form method="POST" action="{{ route('admin.users.reset', $user) }}" class="team-admin-reset-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="password" name="password" class="form-control form-control-sm" placeholder="New password" required minlength="8">
                                                <input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Confirm" required minlength="8">
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Reset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No admins yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .team-admin-table { table-layout: fixed; min-width: 680px; }
    .team-admin-table th, .team-admin-table td { vertical-align: middle; }
    .team-admin-table th:nth-child(1), .team-admin-table td:nth-child(1) { width: 16%; }
    .team-admin-table th:nth-child(2), .team-admin-table td:nth-child(2) { width: 24%; }
    .team-admin-table th:nth-child(3), .team-admin-table td:nth-child(3) { width: 17%; }
    .team-admin-table th:nth-child(4), .team-admin-table td:nth-child(4) { width: 43%; }
    .team-admin-table td:nth-child(2), .team-admin-table td:nth-child(4) { overflow-wrap: anywhere; }
    .team-admin-access { display: grid; gap: .35rem; }
    .team-admin-reset-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; gap: .3rem; align-items: center; }
    .team-admin-reset-form .form-control { min-width: 0; }
    @media(max-width:768px){
        .team-admin-table { min-width: 560px; }
    }
</style>
@endsection
