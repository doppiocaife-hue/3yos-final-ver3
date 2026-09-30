@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div><h1 class="fw-bold mb-1">Inquiries</h1><p class="text-muted mb-0">Track messages from prospective clients.</p></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Client</th>
                    <th class="d-none d-md-table-cell">Inquiry</th>
                    <th class="d-none d-lg-table-cell">Message</th>
                    <th class="d-none d-sm-table-cell">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($inquiries as $inquiry)
                    <tr>
                        <td>
                            <strong>{{ $inquiry->full_name }}</strong>
                            <br><small class="text-muted">{{ $inquiry->email }}</small>
                            <br><small class="text-muted d-md-none">{{ $inquiry->subject }}</small>
                        </td>
                        <td class="d-none d-md-table-cell">
                            {{ $inquiry->subject }}
                            <br><small class="text-muted">{{ $inquiry->category }}</small>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted">{{ \Illuminate\Support\Str::limit($inquiry->message, 80) }}</td>
                        <td class="d-none d-sm-table-cell"><span class="badge-soft">{{ ucwords(str_replace('_', ' ', $inquiry->status)) }}</span></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.inquiries.show', $inquiry) }}">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">No inquiries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
