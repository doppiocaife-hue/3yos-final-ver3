@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card p-4">
        @if($package->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($package->image_path))
            <img src="{{ route('package.image', ['path' => $package->image_path]) }}" alt="{{ $package->name }} catering package" class="w-100 mb-4" style="max-height:420px;object-fit:cover">
        @else
            <div class="package-detail-image-fallback mb-4" role="img" aria-label="{{ $package->name }} package image unavailable">{{ $package->name }} package image unavailable</div>
        @endif
        <h1 class="fw-bold">{{ $package->name }}</h1>
        <p class="text-muted">{{ $package->description }}</p>
        <p>Pricing is estimated from your selected package and guest count when you start a reservation. Our team confirms the final contract price.</p>
        <p><strong>Menu:</strong> {{ $package->menu }}</p>
        <p><strong>Freebies:</strong> {{ $package->freebies }}</p>
        <p><strong>Addons:</strong> {{ $package->addons }}</p>
    </div>
</div>
<style>
.package-detail-image-fallback{display:grid;min-height:220px;place-items:center;border:1px solid var(--line);border-radius:10px;background:#edf2f4;color:var(--muted);font-weight:700}
</style>
@endsection
