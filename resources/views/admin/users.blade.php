@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <h1 class="fw-bold mb-1">Admin Accounts</h1>
        <p class="text-muted mb-0">Create either a primary admin or a team admin and choose the access level for each account.</p>
    </div></div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
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
                                <th>Status</th>
                                <th>Actions</th>
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
                                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $user->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="team-admin-actions">
                                            <details class="team-admin-edit">
                                                <summary class="btn btn-sm btn-outline-primary">Edit name</summary>
                                                <form method="POST" action="{{ route('admin.users.update-name', $user) }}" class="team-admin-edit-form mt-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <label class="form-label small mb-1" for="admin-name-{{ $user->id }}">Administrator name</label>
                                                    <input id="admin-name-{{ $user->id }}" name="name" class="form-control form-control-sm" value="{{ $user->name }}" maxlength="255" required>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary mt-2">Save name</button>
                                                </form>
                                            </details>
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}" data-password-confirm data-password-message="Confirm your administrator password to change this account's access."
                                                data-confirm-message="{{ $user->is_active ? $user->name.' will no longer be able to log in to the admin system. Their account and activity history will be preserved. Disable this administrator?' : $user->name.' will be able to access the admin system again. Enable this administrator?' }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">
                                                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <details class="team-admin-reset">
                                                <summary class="btn btn-sm btn-outline-secondary">Reset password</summary>
                                                <form method="POST" action="{{ route('admin.users.reset', $user) }}" class="team-admin-reset-form mt-2" data-password-confirm data-password-message="Confirm your administrator password before changing this administrator's password.">
                                                    @csrf
                                                    @method('PUT')
                                                    <label class="visually-hidden" for="admin-password-{{ $user->id }}">New password</label>
                                                    <input id="admin-password-{{ $user->id }}" type="password" name="password" class="form-control form-control-sm" placeholder="New password" required minlength="12">
                                                    <label class="visually-hidden" for="admin-password-confirm-{{ $user->id }}">Confirm new password</label>
                                                    <input id="admin-password-confirm-{{ $user->id }}" type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Confirm" required minlength="12">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Reset</button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-3">No admins yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .team-admin-table { table-layout: fixed; min-width: 780px; }
    .team-admin-table th, .team-admin-table td { vertical-align: middle; }
    .team-admin-table th:nth-child(1), .team-admin-table td:nth-child(1) { width: 17%; }
    .team-admin-table th:nth-child(2), .team-admin-table td:nth-child(2) { width: 23%; }
    .team-admin-table th:nth-child(3), .team-admin-table td:nth-child(3) { width: 16%; }
    .team-admin-table th:nth-child(4), .team-admin-table td:nth-child(4) { width: 12%; }
    .team-admin-table th:nth-child(5), .team-admin-table td:nth-child(5) { width: 32%; }
    .team-admin-table td:nth-child(2), .team-admin-table td:nth-child(5) { overflow-wrap: anywhere; }
    .team-admin-actions { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem; }
    .team-admin-edit-form { min-width: 210px; }
    .team-admin-actions summary { cursor: pointer; list-style: none; }
    .team-admin-actions summary::-webkit-details-marker { display: none; }
    .team-admin-reset-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; gap: .3rem; align-items: center; }
    .team-admin-reset-form .form-control { min-width: 0; }
    @media(max-width:768px){
        .team-admin-table { min-width: 780px; }
        .team-admin-reset-form { grid-template-columns: 1fr; }
    }
</style>
@endsection
