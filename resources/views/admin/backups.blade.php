@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <h1 class="fw-bold mb-1">Backups</h1>
    <p class="text-muted mb-4">Create a downloadable snapshot of your catering data.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('admin.backups.create') }}" class="mb-3">@csrf<button class="btn btn-primary">Create Backup</button></form>
    <div class="card p-4">
        <div class="backup-list">
            @forelse($backups as $backup)
                <div class="backup-row">
                    <div><strong>{{ $backup }}</strong><small class="d-block text-muted">{{ number_format(filesize(storage_path('app/backups/' . $backup)) / 1024, 1) }} KB</small></div>
                    <div class="backup-actions">
                        <form method="POST" action="{{ route('admin.backups.download') }}">@csrf<input type="hidden" name="backup" value="{{ $backup }}"><button class="btn btn-sm btn-outline-secondary" type="submit">Download</button></form>
                        <form method="POST" action="{{ route('admin.backups.restore') }}" data-requires-password data-password-message="Restore this backup? Current database data will be replaced.">@csrf<input type="hidden" name="backup" value="{{ $backup }}"><button class="btn btn-sm btn-outline-danger" type="submit">Restore</button></form>
                        <form method="POST" action="{{ route('admin.backups.delete') }}" data-requires-password data-password-message="Permanently delete this backup? This cannot be undone.">@csrf @method('DELETE')<input type="hidden" name="backup" value="{{ $backup }}"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
                    </div>
                </div>
            @empty
                <p class="mb-0 text-muted">No backups found.</p>
            @endforelse
        </div>
    </div>
</div>
<dialog id="backup-password-dialog" aria-labelledby="backup-password-title">
    <form id="backup-password-dialog-form">
        <h2 id="backup-password-title">Confirm your password</h2>
        <p id="backup-password-message" class="text-muted"></p>
        <label for="backup-password-input" class="form-label">Administrator password</label>
        <input id="backup-password-input" type="password" class="form-control" autocomplete="current-password" required>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="backup-password-cancel">Cancel</button>
            <button type="submit" class="btn btn-danger">Continue</button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('backup-password-dialog');
    const dialogForm = document.getElementById('backup-password-dialog-form');
    const passwordInput = document.getElementById('backup-password-input');
    let protectedForm = null;

    document.querySelectorAll('form[data-requires-password]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.passwordConfirmed === 'true') {
                delete form.dataset.passwordConfirmed;
                return;
            }
            event.preventDefault();
            protectedForm = form;
            document.getElementById('backup-password-message').textContent = form.dataset.passwordMessage;
            passwordInput.value = '';
            dialog.showModal();
            passwordInput.focus();
        });
    });

    document.getElementById('backup-password-cancel').addEventListener('click', () => dialog.close());
    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!dialogForm.reportValidity() || !protectedForm) return;

        const confirmation = document.createElement('input');
        confirmation.type = 'hidden';
        confirmation.name = 'password_confirmation';
        confirmation.value = passwordInput.value;
        protectedForm.append(confirmation);
        protectedForm.dataset.passwordConfirmed = 'true';
        const form = protectedForm;
        protectedForm = null;
        dialog.close();
        form.requestSubmit();
    });
})();
</script>
<style>.backup-list{display:grid;gap:.75rem}.backup-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 0;border-bottom:1px solid var(--line)}.backup-row:last-child{border-bottom:0}.backup-row strong{font-size:.85rem}.backup-actions{display:flex;gap:.5rem;flex-wrap:wrap}.backup-actions form{margin:0}@media(max-width:575px){.backup-row{align-items:flex-start;flex-direction:column}.backup-actions{width:100%}.backup-actions form,.backup-actions .btn{flex:1;width:100%}}</style>
@endsection
