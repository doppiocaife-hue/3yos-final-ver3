@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="mb-4">
        <a class="text-decoration-none small" href="{{ route('admin.packages.index') }}">Back to packages</a>
        <h1 class="fw-bold mt-2 mb-1">{{ $package->exists ? 'Edit package' : 'Add package' }}</h1>
    </div>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" enctype="multipart/form-data" data-password-confirm data-password-message="{{ $package->exists ? 'Update this package? Confirm your administrator password to continue.' : 'Add this package? Confirm your administrator password to continue.' }}">
        @csrf
        @if($package->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="package-name">Package name</label>
                <input id="package-name" class="form-control" name="name" value="{{ old('name', $package->name) }}" required>
                <small class="form-text">Use the package name customers will see.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="package-price">Base rate (PHP)</label>
                <input id="package-price" class="form-control" name="price" type="number" min="0" step="0.01" value="{{ old('price', $package->price) }}" required>
                <small class="form-text">The reservation estimate uses this base rate and selected guest count.</small>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" name="is_featured" id="featured" @checked(old('is_featured', $package->is_featured))>
                    <label class="form-check-label" for="featured">Featured package</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" class="form-control" name="description" rows="3">{{ old('description', $package->description) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="menu">Package inclusions / menu</label>
                <textarea id="menu" class="form-control" name="menu" rows="4">{{ old('menu', $package->menu) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="freebies">Freebies</label>
                <textarea id="freebies" class="form-control" name="freebies" rows="4">{{ old('freebies', $package->freebies) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="addons">Optional add-ons</label>
                <textarea id="addons" class="form-control" name="addons" rows="3">{{ old('addons', $package->addons) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="event-type">Best for / event type</label>
                <input id="event-type" class="form-control" name="event_type" value="{{ old('event_type', $package->event_type) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="package-image">Package image</label>
                <input id="package-image" class="form-control" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <small class="form-text">Upload a JPG, PNG, or WebP image up to 5 MB. Leave blank to keep the current image.</small>
                @if($package->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($package->image_path))
                    <img src="{{ route('package.image', ['path' => $package->image_path]) }}" alt="Current {{ $package->name }} package image" class="mt-3" style="max-width:240px;max-height:160px;object-fit:cover">
                @elseif($package->image_path)
                    <p class="form-text mt-2">The current image file is unavailable. Upload a replacement to restore the preview.</p>
                @endif
            </div>
        </div>
        <button class="btn luxury-btn mt-4">{{ $package->exists ? 'Save changes' : 'Create package' }}</button>
    </form>
</div>
@endsection