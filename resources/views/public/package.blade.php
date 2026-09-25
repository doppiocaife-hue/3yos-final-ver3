@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card p-4">
        @if($package->image_path)
            <img src="{{ asset('storage/' . $package->image_path) }}" alt="{{ $package->name }} catering package" class="w-100 mb-4" style="max-height:420px;object-fit:cover">
        @endif
        @if($package->image_path)
            <img src="{{ asset('storage/' . $package->image_path) }}" alt="{{ $package->name }} catering package" class="w-100 mb-4" style="max-height:420px;object-fit:cover">
        @endif
        <h1 class="fw-bold">{{ $package->name }}</h1>
        <p class="text-muted">{{ $package->description }}</p>
        <p>Pricing is estimated from your selected package and guest count when you start a reservation. Our team confirms the final contract price.</p>
        <p><strong>Menu:</strong> {{ $package->menu }}</p>
        <p><strong>Freebies:</strong> {{ $package->freebies }}</p>
        <p><strong>Addons:</strong> {{ $package->addons }}</p>
    </div>
</div>
@endsection
