@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <h1 class="fw-bold mb-1">Gallery</h1>
        <p class="text-muted mb-0">Add event photos and update the public gallery.</p>
    </div></div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="gallery-upload mb-4">
        <h2 class="h5 mb-3">Add photo</h2>
        <form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="new-gallery-image">Image file</label>
                <input id="new-gallery-image" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                <small class="form-text">Upload a JPG, PNG, or WebP image up to 5 MB.</small>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="newFeatured">
                <label class="form-check-label" for="newFeatured">Featured image</label>
            </div>
            <button class="btn luxury-btn" type="submit">Upload photo</button>
        </form>
    </section>

    <div class="row g-3 gallery-grid">
        @forelse($galleryItems as $item)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <article class="gallery-admin-item">
                    <div class="gallery-admin-preview">
                        @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($item->image_path))
                            <img src="{{ route('gallery.image', ['path' => $item->image_path]) }}" alt="Catering event photo" loading="lazy">
                        @else
                            <div class="gallery-admin-missing" role="img" aria-label="Catering event image file unavailable"><span>Image file unavailable</span></div>
                        @endif
                        @if($item->is_featured)
                            <span class="gallery-admin-featured">★ Featured</span>
                        @endif
                    </div>
                    <div class="gallery-admin-controls">
                        <div class="gallery-admin-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm gallery-edit-toggle" aria-expanded="false" aria-controls="gallery-editor-{{ $item->id }}">Edit</button>
                            <form method="POST" action="{{ route('admin.gallery.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                        <div class="gallery-admin-editor-body" id="gallery-editor-{{ $item->id }}" hidden>
                            <form method="POST" action="{{ route('admin.gallery.update', $item) }}" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <label class="form-label" for="gallery-image-{{ $item->id }}">Replace image</label>
                                <input id="gallery-image-{{ $item->id }}" class="form-control mb-2" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured{{ $item->id }}" @checked($item->is_featured)>
                                    <label class="form-check-label" for="featured{{ $item->id }}">Featured image</label>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Save changes</button>
                                    <button class="btn btn-outline-secondary btn-sm gallery-edit-cancel" type="button">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-4">No gallery images yet.</div>
        @endforelse
    </div>
</div>

<style>
    .gallery-upload { max-width: 540px; padding: 1.1rem 1.25rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .gallery-admin-item { overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); height: 100%; display: flex; flex-direction: column; }
    .gallery-admin-preview { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: #eaf0f2; }
    .gallery-admin-preview img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .gallery-admin-missing { display: grid; width: 100%; height: 100%; place-items: center; background: #eaf0f2; color: #71808b; font-size: .8rem; font-weight: 700; }
    body.dark-mode .gallery-admin-missing { background: #22343f; color: #b4c2c9; }
    .gallery-admin-featured { position: absolute; top: .65rem; left: .65rem; padding: .25rem .55rem; border-radius: 999px; background: var(--gold); color: #2c2014; font-size: .68rem; font-weight: 800; box-shadow: 0 2px 6px rgba(21,37,55,.18); }
    .gallery-admin-controls { padding: .85rem .9rem 1rem; }
    .gallery-admin-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .gallery-admin-actions form { margin: 0; }
    .gallery-admin-editor-body { margin-top: .85rem; padding-top: .85rem; border-top: 1px solid var(--line); }
    @media (max-width: 575.98px) {
        .gallery-upload { max-width: none; padding: 1rem; }
    }
</style>
<script>
document.querySelectorAll('.gallery-edit-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
        const body = document.getElementById(btn.getAttribute('aria-controls'));
        if (!body) return;
        const willOpen = body.hasAttribute('hidden');
        body.toggleAttribute('hidden', !willOpen);
        btn.setAttribute('aria-expanded', String(willOpen));
    });
});
document.querySelectorAll('.gallery-edit-cancel').forEach((btn) => {
    btn.addEventListener('click', () => {
        const body = btn.closest('.gallery-admin-editor-body');
        if (!body) return;
        body.setAttribute('hidden', '');
        const toggle = document.querySelector(`.gallery-edit-toggle[aria-controls="${body.id}"]`);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
});
</script>
@endsection
