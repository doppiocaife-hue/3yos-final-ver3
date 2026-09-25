@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="mb-4">
        <h1 class="fw-bold mb-1">Gallery</h1>
        <p class="text-muted mb-0">Add event photos and update the public gallery.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="gallery-upload mb-4">
        <h2 class="h5 mb-3">Add photo</h2>
        <form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data" data-password-confirm data-password-message="Add this gallery photo? Confirm your administrator password to continue.">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="new-gallery-image">Image file</label>
                    <input id="new-gallery-image" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                </div>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="newFeatured">
                <label class="form-check-label" for="newFeatured">Featured image</label>
            </div>
            <button class="btn luxury-btn mt-3" type="submit">Upload photo</button>
        </form>
    </section>

    <div class="row g-4">
        @forelse($galleryItems as $item)
            <div class="col-md-6">
                <article class="gallery-admin-item">
                    <div class="gallery-admin-preview">
                        <img src="{{ route('gallery.image', ['path' => $item->image_path]) }}" alt="Catering event photo" loading="lazy">
                        @if($item->is_featured)
                            <span class="gallery-admin-featured">Featured</span>
                        @endif
                    </div>
                    <details class="gallery-admin-editor">
                        <summary>Image controls</summary>
                        <div class="gallery-admin-editor-body">
                            <form method="POST" action="{{ route('admin.gallery.update', $item) }}" enctype="multipart/form-data" data-password-confirm data-password-message="Save these image changes? Confirm your administrator password to continue.">
                                @csrf @method('PUT')
                                <label class="form-label" for="gallery-image-{{ $item->id }}">Replace image</label>
                                <input id="gallery-image-{{ $item->id }}" class="form-control mb-2" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured{{ $item->id }}" @checked($item->is_featured)>
                                    <label class="form-check-label" for="featured{{ $item->id }}">Featured image</label>
                                </div>
                                <button class="btn btn-outline-secondary btn-sm mt-3" type="submit">Save changes</button>
                            </form>
                            <form class="mt-2" method="POST" action="{{ route('admin.gallery.destroy', $item) }}" data-password-confirm data-password-message="Delete this gallery image? Confirm your administrator password to continue.">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </details>
                </article>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-4">No gallery images yet.</div>
        @endforelse
    </div>
</div>

<style>
    .gallery-upload { padding: 1rem; border: 1px solid var(--line); border-radius: 10px; }
    .gallery-admin-item { position: relative; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
    .gallery-admin-preview { position: relative; height: 220px; overflow: hidden; }
    .gallery-admin-preview img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .gallery-admin-featured { position: absolute; top: .75rem; left: .75rem; padding: .25rem .5rem; border-radius: 999px; background: var(--gold); color: #2c2014; font-size: .68rem; font-weight: 800; }
    .gallery-admin-editor { position: absolute; right: .65rem; bottom: .65rem; z-index: 2; }
    .gallery-admin-editor summary { padding: .45rem .7rem; border-radius: 7px; background: var(--surface); color: var(--teal-dark); font-size: .78rem; font-weight: 700; cursor: pointer; list-style: none; box-shadow: 0 2px 8px rgba(21,37,55,.18); }
    .gallery-admin-editor summary::-webkit-details-marker { display: none; }
    .gallery-admin-editor-body { position: absolute; right: 0; bottom: calc(100% + .5rem); width: min(360px, calc(100vw - 3rem)); max-height: 70vh; overflow-y: auto; padding: .85rem; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); box-shadow: 0 12px 28px rgba(21,37,55,.2); }
</style>
@endsection