@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card p-4 p-lg-5">
                <div class="mb-4">
                    <span class="hero-badge">Inquiry form</span>
                    <h1 class="fw-bold mt-3 mb-2">Let’s talk about your event</h1>
                    <p class="text-muted mb-0">Tell us what you need and we’ll get back with the best options for your celebration.</p>
                </div>
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('inquiry.store') }}">
                    @csrf
                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">
                    <input type="hidden" name="form_started" value="{{ now()->timestamp }}">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">Contact Number</label><input type="tel" name="contact_number" value="{{ old('contact_number') }}" class="form-control form-control-lg" inputmode="tel" autocomplete="tel" minlength="11" maxlength="13" pattern="(?:\+63[0-9]{10}|09[0-9]{9})" placeholder="09XXXXXXXXX or +639XXXXXXXXX" title="Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces." required><small class="form-text">Enter 09 followed by 9 digits or +63 followed by 10 digits.</small></div>
                        <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">Subject</label><input type="text" name="subject" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">Category</label><select name="category" class="form-select form-select-lg" required><option value="">Select</option><option>General Inquiry</option><option>Reservation</option><option>Packages</option><option>Pricing</option><option>Custom Event</option><option>Others</option></select></div>
                        <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" required></textarea></div>
                        <div class="col-12">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            @error('g-recaptcha-response')
                                <div class="alert alert-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary px-4 py-2">Submit Inquiry</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endsection
