@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('admin.inquiries') }}">← Back to inquiries</a>
            <h1 class="fw-bold mb-1">{{ $inquiry->subject }}</h1>
            <p class="text-muted mb-0">{{ $inquiry->category }} · received {{ $inquiry->created_at->format('M j, Y g:i A') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Delete this inquiry? This cannot be undone.');">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">Delete</button>
        </form>
    </div>
    
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card p-3 h-100">
                <h5 class="fw-bold mb-3">Client details</h5>
                <p class="mb-1"><strong>{{ $inquiry->full_name }}</strong></p>
                <p class="mb-1">
                    <a href="mailto:{{ $inquiry->email }}" class="text-decoration-none">{{ $inquiry->email }}</a>
                </p>
                <p class="mb-3">
                    <a href="tel:{{ $inquiry->contact_number }}" class="text-decoration-none">{{ $inquiry->contact_number }}</a>
                </p>
                <hr>
                <p class="mb-1"><strong>Message</strong></p>
                <p class="text-muted" style="white-space:pre-line;word-break:break-word;overflow-wrap:break-word">{{ $inquiry->message }}</p>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card p-3">
                <h5 class="fw-bold mb-2">Reply by email</h5>
                <p class="text-muted small mb-3">Sending records the reply and changes this inquiry to Responded.</p>
                <form method="POST" action="{{ route('admin.inquiries.reply', $inquiry) }}">
                    @csrf
                    <textarea class="form-control @error('reply') is-invalid @enderror" name="reply" rows="8" required style="resize:vertical;min-height:200px">{{ old('reply', $inquiry->admin_reply) }}</textarea>
                    @error('reply')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn luxury-btn mt-3 w-100 w-sm-auto" type="submit">Send reply</button>
                </form>
            </div>
            
            @if($inquiry->admin_reply)
                <div class="card p-3 mt-4">
                    <h5 class="fw-bold mb-2">Last sent reply</h5>
                    <p class="text-muted mb-2" style="white-space:pre-line;word-break:break-word;overflow-wrap:break-word">{{ $inquiry->admin_reply }}</p>
                    <small class="text-muted">{{ $inquiry->replied_at?->format('M j, Y g:i A') }}</small>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
