@extends('layouts.app')

@section('title', 'Book an event | 3YOS Catering')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="page-heading">
        <div class="eyebrow">Reservation request</div>
        <h1 class="fw-bold mt-2 mb-2">Plan your perfect event.</h1>
        <p class="text-muted mb-0">We accept up to three events each day so every celebration gets the attention it deserves.</p>
    </div>

    @if(session('reservation_code') || (isset($reservation) && $reservation))
        @php($statusCode = session('reservation_code') ?: ($reservation->reservation_code ?? null))
        @php($statusLabel = session('reservation_status') ?: ($reservation->status ?? 'pending'))
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8">
                <div class="alert alert-info mb-0 px-3 py-2 text-start">
                    <div class="small text-uppercase fw-bold opacity-75">Current status</div>
                    <div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $statusLabel) }}</div>
                    <div class="small mt-1"><strong>ID:</strong> {{ $statusCode }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 g-lg-5 reservation-shell">
        <div class="col-lg-4">
            <aside class="reservation-sidebar">
                <div class="eyebrow mb-2">Before you submit</div>
                <h2>We’ll make it easy.</h2>
                <ul class="reservation-checklist">
                    <li>Tell us your guest count and preferred date.</li>
                    <li>Choose a package that fits your budget and vibe.</li>
                    <li>We’ll confirm availability and recommend the right setup.</li>
                </ul>
                <div class="reservation-tile">
                    <strong>Best for</strong>
                    <p>Weddings, debut parties, birthdays, corporate events, and intimate family celebrations.</p>
                </div>
            </aside>
        </div>

        <div class="col-lg-8">
            <div class="form-card p-4 p-lg-5">
                @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @if(session('success'))
                    <div id="reservation-toast" class="floating-toast show" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="floating-toast__icon">✓</div>
                        <div class="floating-toast__content">
                            <strong>Reservation sent</strong>
                            <div>{{ session('success') }}</div>
                        </div>
                        <button type="button" class="floating-toast__close" aria-label="Close notification">&times;</button>
                    </div>
                @endif

                <form method="POST" action="{{ route('reservation.store') }}" id="reservation-form">@csrf<input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off"><input type="hidden" name="form_started" value="{{ now()->timestamp }}">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full name</label><input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control" placeholder="Juan dela Cruz" autocomplete="name" required></div>
                        <div class="col-md-6"><label class="form-label">Contact number</label><input type="tel" name="contact_number" value="{{ old('contact_number') }}" class="form-control" inputmode="tel" autocomplete="tel" minlength="11" maxlength="13" pattern="(?:\+63[0-9]{10}|09[0-9]{9})" placeholder="09XXXXXXXXX or +639XXXXXXXXX" title="Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces." required><small class="form-text">Enter 09 followed by 9 digits or +63 followed by 10 digits.</small></div>
                        <div class="col-md-6"><label class="form-label">Email address</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Complete address</label><input type="text" name="address" value="{{ old('address') }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Event type</label><select name="event_type" class="form-select" required><option value="">Select an event type</option>@foreach(['Wedding','Birthday','Debut','Anniversary','Corporate Event','Baptism','Graduation','Other'] as $type)<option value="{{ $type }}" @selected(old('event_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                        @php($preselectedPackageId = old('package_id', request()->query('package')))
                        @php($selectedPackage = $packages->firstWhere('id', $preselectedPackageId))
                        <div class="col-md-6 package-picker-field">
                            <label class="form-label" for="package-picker-toggle">Catering package</label>
                            <select name="package_id" id="package_id" class="form-select" required>
                                <option value="">Select a package</option>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}" data-price="{{ $package->price }}" @selected($preselectedPackageId == $package->id)>{{ $package->name }}</option>
                                @endforeach
                            </select>
                            <div class="package-picker" id="package-picker" hidden>
                                <button type="button" id="package-picker-toggle" class="form-select package-picker-toggle" aria-haspopup="listbox" aria-expanded="false" aria-required="true" aria-controls="package-picker-panel">
                                    <span id="package-picker-value">{{ $selectedPackage?->name ?? 'Select a package' }}</span>
                                </button>
                                <div class="package-picker-panel" id="package-picker-panel" hidden>
                                    <div class="package-picker-options" role="listbox" aria-label="Catering packages">
                                        @foreach($packages as $package)
                                            <button type="button" class="package-picker-option" role="option" id="package-option-{{ $package->id }}" data-package-option data-value="{{ $package->id }}" data-name="{{ $package->name }}" data-description="{{ $package->description }}" data-menu="{{ $package->menu }}" data-freebies="{{ $package->freebies }}" data-addons="{{ $package->addons }}" aria-selected="{{ (string) $preselectedPackageId === (string) $package->id ? 'true' : 'false' }}" tabindex="-1">
                                                <span>{{ $package->name }}</span>
                                                <small>Preview package</small>
                                            </button>
                                        @endforeach
                                    </div>
                                    <section class="package-picker-preview" aria-live="polite">
                                        <h3 id="package-preview-name">{{ $selectedPackage?->name ?? 'Package preview' }}</h3>
                                        <p id="package-preview-description">{{ $selectedPackage?->description ?? 'Hover over or focus a package to preview its details.' }}</p>
                                        <div class="package-preview-detail" id="package-preview-menu-wrap" @if(! $selectedPackage?->menu) hidden @endif><strong>Menu</strong><span id="package-preview-menu">{{ $selectedPackage?->menu }}</span></div>
                                        <div class="package-preview-detail" id="package-preview-freebies-wrap" @if(! $selectedPackage?->freebies) hidden @endif><strong>Included</strong><span id="package-preview-freebies">{{ $selectedPackage?->freebies }}</span></div>
                                        <div class="package-preview-detail" id="package-preview-addons-wrap" @if(! $selectedPackage?->addons) hidden @endif><strong>Optional add-ons</strong><span id="package-preview-addons">{{ $selectedPackage?->addons }}</span></div>
                                    </section>
                                </div>
                            </div>
                            <small id="package-picker-error" class="form-text text-danger" hidden>Select a catering package to continue.</small>
                            <small class="form-text">Choose the package that best fits your event.</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Event date</label><input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" min="{{ now()->addDays(2)->toDateString() }}" class="form-control" required><div id="date-availability" class="date-availability form-text">Choose a date at least 2 days in advance.</div></div>
                        <div class="col-md-6 clock-time-field">
                            <label class="form-label" for="clock-time-toggle">Event time</label>
                            <input type="time" name="event_time" id="event_time" value="{{ old('event_time') }}" class="form-control" required>
                            <div class="clock-time-picker" id="clock-time-picker" hidden>
                                <button type="button" id="clock-time-toggle" class="form-control clock-time-toggle" aria-haspopup="dialog" aria-expanded="false" aria-required="true" aria-controls="clock-time-panel">
                                    <span id="clock-time-value">Select a time</span><span class="clock-time-icon" aria-hidden="true"></span>
                                </button>
                                <section class="clock-time-panel" id="clock-time-panel" role="dialog" aria-label="Select event time" hidden>
                                    <div class="clock-time-header">
                                        <div class="clock-time-readout">
                                            <button type="button" id="clock-current-hour" aria-label="Choose hour">12</button>
                                            <span>:</span>
                                            <button type="button" id="clock-current-minute" aria-label="Choose minutes">00</button>
                                        </div>
                                        <div class="clock-time-period" role="group" aria-label="AM or PM">
                                            <button type="button" data-clock-period="AM" aria-pressed="true">AM</button>
                                            <button type="button" data-clock-period="PM" aria-pressed="false">PM</button>
                                        </div>
                                    </div>
                                    <div class="clock-time-face" id="clock-time-face" role="group" aria-label="Choose hour">
                                        <div class="clock-time-hand" id="clock-time-hand"></div>
                                        <div id="clock-time-numbers"></div>
                                    </div>
                                    <div class="clock-time-actions">
                                        <button type="button" id="clock-time-cancel" class="btn btn-outline-secondary btn-sm">Cancel</button>
                                        <button type="button" id="clock-time-apply" class="btn btn-primary btn-sm">Use this time</button>
                                    </div>
                                </section>
                            </div>
                            <small id="clock-time-error" class="form-text text-danger" hidden>Select an event time to continue.</small>
                            <small class="form-text">Choose a time using the clock.</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Venue</label><input type="text" name="venue" value="{{ old('venue') }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Expected guests</label><input type="number" name="guest_count" id="guest_count" value="{{ old('guest_count', request()->query('guests')) }}" min="1" max="1000" class="form-control" required><small class="form-text">Enter the total number of attendees.</small></div>
                        <div class="col-md-6"><label class="form-label" for="estimated-budget-display">Estimated package total</label><output id="estimated-budget-display" class="form-control" aria-live="polite">Choose a package and guest count to see an estimate.</output><small class="form-text">Calculated automatically from the package rate and guest count; the final contract price is confirmed by our team.</small></div>
                        <div class="col-12"><label class="form-label">Additional services</label><textarea name="additional_services" class="form-control" rows="2">{{ old('additional_services') }}</textarea></div>
                        <div class="col-12"><label class="form-label">Special requests</label><textarea name="special_requests" class="form-control" rows="2">{{ old('special_requests') }}</textarea></div>
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea name="additional_notes" class="form-control" rows="2">{{ old('additional_notes') }}</textarea></div>
                        <div class="col-12">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            @error('g-recaptcha-response')
                                <div class="alert alert-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12"><button type="submit" id="submit-reservation" class="btn btn-primary">Submit reservation request</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .page-heading{padding:1rem 0 2rem}
    .page-heading h1{font-size:clamp(2.4rem,4vw,4rem);line-height:1.05;letter-spacing:-.04em}
    .reservation-shell{margin-top:1rem}
    .reservation-sidebar{background:linear-gradient(160deg,#f5ebdf,#f0e2d0);border:1px solid rgba(109,48,36,.08);border-radius:24px;padding:2rem;position:sticky;top:90px}
    .reservation-sidebar h2{font-size:clamp(1.8rem,2vw,2.3rem);margin-bottom:1rem}
    .reservation-checklist{list-style:none;padding:0;margin:1rem 0 1.5rem;display:grid;gap:.9rem}
    .reservation-checklist li{position:relative;padding-left:1.8rem;color:var(--muted);line-height:1.6}
    .reservation-checklist li:before{content:'✓';position:absolute;left:0;top:0;color:var(--wine);font-weight:800}
    .reservation-tile{margin-top:1rem;padding:1.2rem;background:#f7efe7;border-radius:18px;border:1px solid rgba(109,48,36,.08)}
    .reservation-tile strong{display:block;margin-bottom:.25rem}
    .reservation-tile p{margin:0;color:var(--muted);line-height:1.6}
    .form-card{background:#fdf9f5;border:1px solid rgba(109,48,36,.08);border-radius:24px;box-shadow:0 22px 50px rgba(32,32,29,.06)}
    .form-label{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
    .form-control,.form-select{border:1px solid #e7ddd0;border-radius:14px;padding:.8rem 1rem;background:#fff;color:var(--ink)}
    .form-control:focus,.form-select:focus{border-color:var(--wine);box-shadow:0 0 0 .2rem rgba(109,48,36,.12)}
    .date-availability{display:block;margin-top:.45rem;font-size:.82rem}
    .clock-time-field{position:relative;z-index:2}
    .clock-time-picker{position:relative}
    .clock-time-toggle{display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left}
    .clock-time-icon{position:relative;width:18px;height:18px;flex:0 0 18px;border:1.5px solid currentColor;border-radius:50%}
    .clock-time-icon:before,.clock-time-icon:after{position:absolute;left:50%;top:50%;width:1.5px;background:currentColor;content:'';transform-origin:50% 0}
    .clock-time-icon:before{height:5px;transform:translate(-50%,-1px)}
    .clock-time-icon:after{height:4px;transform:translate(-50%,-1px) rotate(120deg)}
    .clock-time-panel{position:fixed;left:16px;top:16px;z-index:1080;width:min(320px,calc(100vw - 2rem));padding:1rem;border:1px solid #e7ddd0;border-radius:14px;background:#fff;box-shadow:0 18px 42px rgba(32,32,29,.2)}
    .clock-time-header{display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:.8rem}
    .clock-time-readout{display:flex;align-items:center;gap:.15rem;color:var(--wine);font-size:1.65rem;font-weight:700}
    .clock-time-readout button{min-width:2.5rem;padding:.2rem .3rem;border:0;border-radius:6px;background:transparent;color:inherit;font:inherit}
    .clock-time-readout button[aria-pressed="true"],.clock-time-readout button:hover{background:#f6eee5}
    .clock-time-period{display:grid;gap:.18rem}
    .clock-time-period button{padding:.15rem .4rem;border:1px solid #e7ddd0;border-radius:5px;background:#fff;color:var(--muted);font-size:.68rem;font-weight:700}
    .clock-time-period button[aria-pressed="true"]{border-color:var(--wine);background:var(--wine);color:#fff}
    .clock-time-face{--clock-number-radius:72px;position:relative;width:min(230px,100%);aspect-ratio:1;margin:0 auto .85rem;border-radius:50%;background:#f6eee5}
    .clock-time-hand{position:absolute;left:calc(50% - 1px);top:50%;z-index:0;width:2px;height:37%;border-radius:2px;background:var(--terracotta);transform:rotate(180deg);transform-origin:50% 0;pointer-events:none}
    .clock-time-hand:after{position:absolute;left:50%;bottom:-4px;width:9px;height:9px;border-radius:50%;background:var(--wine);content:'';transform:translateX(-50%)}
    .clock-time-number{position:absolute;left:50%;top:50%;z-index:1;display:grid;place-items:center;width:36px;height:36px;padding:0;border:0;border-radius:50%;background:transparent;color:var(--ink);font-size:.83rem;font-weight:700;transform:translate(-50%,-50%) rotate(var(--clock-angle)) translateY(calc(-1 * var(--clock-number-radius))) rotate(calc(var(--clock-angle) * -1))}
    .clock-time-number:hover,.clock-time-number:focus-visible,.clock-time-number[aria-pressed="true"]{outline:0;background:var(--wine);color:#fff}
    .clock-time-actions{display:flex;justify-content:flex-end;gap:.5rem}
    body.dark-mode .clock-time-toggle{border-color:#555047;background:#201f1d;color:#f5f1e9}
    body.dark-mode .clock-time-panel{border-color:#4f4942;background:#201f1d;color:#f5f1e9}
    body.dark-mode .clock-time-readout button[aria-pressed="true"],body.dark-mode .clock-time-readout button:hover{background:#332820}
    body.dark-mode .clock-time-period button{border-color:#4f4942;background:#201f1d;color:#c9c3b9}
    body.dark-mode .clock-time-period button[aria-pressed="true"]{border-color:var(--wine);background:var(--wine);color:#fff}
    body.dark-mode .clock-time-face{background:#29231f}
    body.dark-mode .clock-time-number{color:#f5f1e9}
    body.dark-mode .clock-time-number:hover,body.dark-mode .clock-time-number:focus-visible,body.dark-mode .clock-time-number[aria-pressed="true"]{background:#b66545;color:#fff}
    .package-picker-field{position:relative;z-index:2}
    body.package-picker-open .package-picker-field{z-index:1081}
    body.package-picker-open .clock-time-field{visibility:hidden}
    .package-picker{position:relative}
    .package-picker-toggle{display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left}
    .package-picker-toggle:after{content:'';width:.55rem;height:.55rem;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-.25rem .2rem 0 .75rem;flex:0 0 auto}
    .package-picker-toggle[aria-expanded="true"]:after{transform:rotate(225deg);margin-top:.3rem}
    .package-picker-panel{position:fixed;top:0;left:0;z-index:1080;display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);grid-template-rows:minmax(0,1fr);width:min(560px,calc(100vw - 2rem));max-width:calc(100vw - 2rem);max-height:min(390px,65vh);min-width:0;overflow:hidden;border:1px solid #e7ddd0;border-radius:14px;background:#fff;box-shadow:0 18px 42px rgba(32,32,29,.18)}
    .package-picker-options{min-width:0;min-height:0;overflow-y:auto;padding:.4rem}
    .package-picker-option{display:flex;flex-direction:column;align-items:flex-start;gap:.18rem;width:100%;padding:.65rem .75rem;border:0;border-radius:8px;background:transparent;color:var(--ink);text-align:left}
    .package-picker-option:hover,.package-picker-option:focus-visible,.package-picker-option[aria-selected="true"]{outline:0;background:#f6eee5;color:var(--wine)}
    .package-picker-option span{font-weight:700}
    .package-picker-option small{color:var(--muted);font-size:.72rem}
    .package-picker-preview{min-width:0;min-height:0;overflow-y:auto;padding:1rem 1.1rem;background:#f8f3ec;border-left:1px solid #e7ddd0}
    .package-picker-preview h3{margin:0 0 .45rem;color:var(--wine);font-family:'Playfair Display',Georgia,serif;font-size:1.25rem}
    .package-picker-preview p{margin:0 0 .8rem;color:var(--muted);font-size:.83rem;line-height:1.5}
    .package-preview-detail{display:grid;gap:.15rem;padding:.55rem 0;border-top:1px solid rgba(109,48,36,.12);font-size:.78rem;line-height:1.45}
    .package-preview-detail strong{color:var(--ink);font-size:.67rem;text-transform:uppercase}
    .package-preview-detail span{min-width:0;color:var(--muted);overflow-wrap:anywhere}
    body.dark-mode .package-picker-toggle{background:#201f1d;color:#f5f1e9;border-color:#555047}
    body.dark-mode .package-picker-panel{border-color:#4f4942;background:#201f1d}
    body.dark-mode .package-picker-option{color:#f5f1e9}
    body.dark-mode .package-picker-option:hover,body.dark-mode .package-picker-option:focus-visible,body.dark-mode .package-picker-option[aria-selected="true"]{background:#332820;color:#f1c29b}
    body.dark-mode .package-picker-option small,body.dark-mode .package-picker-preview p,body.dark-mode .package-preview-detail span{color:#c9c3b9}
    body.dark-mode .package-picker-preview{background:#29231f;border-color:#4f4942}
    body.dark-mode .package-picker-preview h3,body.dark-mode .package-preview-detail strong{color:#f1c29b}
    @media(max-width:575px){.package-picker-panel{grid-template-columns:1fr;max-height:min(390px,65vh)}.package-picker-options{max-height:175px}.package-picker-preview{border-top:1px solid #e7ddd0;border-left:0}}
    .floating-toast{position:fixed;right:1.25rem;bottom:1.25rem;display:flex;align-items:center;gap:.9rem;width:min(360px,calc(100vw - 2rem));background:#1f1c1a;color:#fff;padding:1rem 1rem;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.18);opacity:0;transform:translateY(20px);transition:.28s ease;z-index:2000}
    .floating-toast.show{opacity:1;transform:translateY(0)}
    .floating-toast__icon{display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.12);font-weight:800;color:#d8f7d0}
    .floating-toast__content{flex:1;font-size:.92rem}
    .floating-toast__content strong{display:block;margin-bottom:.15rem}
    .floating-toast__close{border:0;background:transparent;color:#fff;opacity:.8;font-size:1.4rem;line-height:1}
    @media (max-width:991.98px){.reservation-sidebar{position:static}} 
    @media (max-width:575px){.page-heading{padding-top:.5rem}.form-card{padding:1.25rem!important}}
</style>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
const reservationToast = document.getElementById('reservation-toast');
if (reservationToast) {
    const closeButton = reservationToast.querySelector('.floating-toast__close');
    const hideToast = () => reservationToast.classList.remove('show');
    setTimeout(hideToast, 6000);
    closeButton?.addEventListener('click', hideToast);
}

const dateInput=document.getElementById('event_date'), availability=document.getElementById('date-availability'), submitButton=document.getElementById('submit-reservation');
if (dateInput && availability && submitButton) {
    const minReservationDate = new Date();
    minReservationDate.setDate(minReservationDate.getDate() + 2);

    const formatDateInputValue = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    dateInput.addEventListener('change', async () => { if (!dateInput.value) return;
        const selectedDate = new Date(dateInput.value + 'T00:00:00');
        const earliestAllowed = new Date(formatDateInputValue(minReservationDate) + 'T00:00:00');

        if (selectedDate < earliestAllowed) {
            availability.textContent='Reservations must be booked at least 2 days in advance.';
            availability.className='date-availability text-danger';
            submitButton.disabled=true;
            return;
        }

        availability.textContent='Checking availability…'; submitButton.disabled=true; try { const response=await fetch(`{{ route('reservation.availability') }}?date=${encodeURIComponent(dateInput.value)}`); const data=await response.json(); if (data.available) { availability.textContent=`Available — ${data.remaining} event slot${data.remaining===1?'':'s'} remaining.`; availability.className='date-availability text-success'; submitButton.disabled=false; } else { availability.textContent='This date is fully booked. Please choose another date.'; availability.className='date-availability text-danger'; } } catch { availability.textContent='We could not check this date. Please try again.'; availability.className='date-availability text-danger'; } });
}

const packageInput = document.getElementById('package_id');
const guestCountInput = document.getElementById('guest_count');
const budgetOutput = document.getElementById('estimated-budget-display');
const pesoFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const packagePicker = document.getElementById('package-picker');
const packagePickerToggle = document.getElementById('package-picker-toggle');
const packagePickerPanel = document.getElementById('package-picker-panel');
const packagePickerValue = document.getElementById('package-picker-value');
const packageOptions = [...document.querySelectorAll('[data-package-option]')];

if (packageInput && packagePicker && packagePickerToggle && packagePickerPanel) {
    packageInput.hidden = true;
    packageInput.required = false;
    packageInput.setAttribute('aria-hidden', 'true');
    packageInput.tabIndex = -1;
    packagePicker.hidden = false;

    const setPreview = (option) => {
        document.getElementById('package-preview-name').textContent = option?.dataset.name || 'Package preview';
        document.getElementById('package-preview-description').textContent = option?.dataset.description || 'Hover over or focus a package to preview its details.';

        for (const [key, id] of [['menu', 'menu'], ['freebies', 'freebies'], ['addons', 'addons']]) {
            const detail = document.getElementById(`package-preview-${id}`);
            const wrapper = document.getElementById(`package-preview-${id}-wrap`);
            detail.textContent = option?.dataset[key] || '';
            wrapper.hidden = !option?.dataset[key];
        }
    };
    const closePackagePicker = (returnFocus = false) => {
        packagePickerPanel.hidden = true;
        packagePickerToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('package-picker-open');
        if (returnFocus) packagePickerToggle.focus();
    };
    const alignPackagePickerPanel = () => {
        const fieldBounds = packagePicker.getBoundingClientRect();
        const panelWidth = Math.min(560, window.innerWidth - 32);
        const panelHeight = packagePickerPanel.getBoundingClientRect().height;
        const left = Math.max(16, Math.min(fieldBounds.left, window.innerWidth - panelWidth - 16));
        const spaceBelow = window.innerHeight - fieldBounds.bottom - 16;
        const maxTop = Math.max(16, window.innerHeight - panelHeight - 16);
        const preferredTop = spaceBelow >= panelHeight + 6
            ? fieldBounds.bottom + 6
            : fieldBounds.top - panelHeight - 6;
        const top = Math.max(16, Math.min(preferredTop, maxTop));

        packagePickerPanel.style.left = `${left}px`;
        packagePickerPanel.style.top = `${top}px`;
    };
    const openPackagePicker = (focusOption = false) => {
        const otherPanel = document.getElementById('clock-time-panel');
        const otherToggle = document.getElementById('clock-time-toggle');
        if (otherPanel) otherPanel.hidden = true;
        otherToggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.add('package-picker-open');
        packagePickerPanel.hidden = false;
        packagePickerToggle.setAttribute('aria-expanded', 'true');
        alignPackagePickerPanel();
        if (focusOption) {
            (packageOptions.find((option) => option.dataset.value === packageInput.value) || packageOptions[0])?.focus();
        }
    };

    setPreview(packageOptions.find((option) => option.dataset.value === packageInput.value) || null);
    packagePickerToggle.addEventListener('click', () => {
        if (packagePickerPanel.hidden) openPackagePicker(true);
        else closePackagePicker();
    });
    packagePickerToggle.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openPackagePicker(true);
        }
    });

    packageOptions.forEach((option, index) => {
        option.addEventListener('pointerenter', () => setPreview(option));
        option.addEventListener('focus', () => setPreview(option));
        option.addEventListener('click', () => {
            packageInput.value = option.dataset.value;
            packagePickerValue.textContent = option.dataset.name;
            packagePickerToggle.removeAttribute('aria-invalid');
            document.getElementById('package-picker-error').hidden = true;
            packageOptions.forEach((item) => item.setAttribute('aria-selected', String(item === option)));
            setPreview(option);
            packageInput.dispatchEvent(new Event('change', { bubbles: true }));
            closePackagePicker(true);
        });
        option.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const offset = event.key === 'ArrowDown' ? 1 : -1;
                packageOptions[(index + offset + packageOptions.length) % packageOptions.length]?.focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closePackagePicker(true);
            }
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (!packagePicker.contains(event.target)) closePackagePicker();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !packagePickerPanel.hidden) closePackagePicker(true);
    });
    window.addEventListener('resize', () => {
        if (!packagePickerPanel.hidden) alignPackagePickerPanel();
    });
    window.addEventListener('scroll', () => {
        if (!packagePickerPanel.hidden) alignPackagePickerPanel();
    }, true);

    document.getElementById('reservation-form').addEventListener('submit', (event) => {
        if (packageInput.value) return;

        event.preventDefault();
        packagePickerToggle.setAttribute('aria-invalid', 'true');
        document.getElementById('package-picker-error').hidden = false;
        packagePickerToggle.focus();
    });
}

const timeInput = document.getElementById('event_time');
const clockTimePicker = document.getElementById('clock-time-picker');
const clockTimeToggle = document.getElementById('clock-time-toggle');
const clockTimePanel = document.getElementById('clock-time-panel');
const clockTimeNumbers = document.getElementById('clock-time-numbers');
const clockTimeValue = document.getElementById('clock-time-value');
const clockTimeFace = document.getElementById('clock-time-face');
const clockTimeHand = document.getElementById('clock-time-hand');
const selectedHourOutput = document.getElementById('clock-current-hour');
const selectedMinuteOutput = document.getElementById('clock-current-minute');
let selectedHour = 12;
let selectedMinute = 0;
let selectedPeriod = 'AM';
let clockMode = 'hours';

if (timeInput && clockTimePicker && clockTimeToggle && clockTimePanel) {
    timeInput.hidden = true;
    timeInput.required = false;
    timeInput.setAttribute('aria-hidden', 'true');
    timeInput.tabIndex = -1;
    clockTimePicker.hidden = false;

    const updateClockReadout = () => {
        selectedHourOutput.textContent = String(selectedHour);
        selectedMinuteOutput.textContent = String(selectedMinute).padStart(2, '0');
        document.querySelectorAll('[data-clock-period]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.clockPeriod === selectedPeriod));
        });
    };
    const renderClockFace = () => {
        const choosingHours = clockMode === 'hours';
        const values = choosingHours ? Array.from({ length: 12 }, (_, index) => index + 1) : Array.from({ length: 12 }, (_, index) => index * 5);
        const selectedValue = choosingHours ? selectedHour : selectedMinute;
        const angleStep = choosingHours ? (selectedHour % 12) * 30 : (selectedMinute / 5) * 30;
        clockTimeFace.setAttribute('aria-label', choosingHours ? 'Choose hour' : 'Choose minutes');
        clockTimeHand.style.transform = `rotate(${180 + angleStep}deg)`;
        clockTimeNumbers.replaceChildren();

        values.forEach((value) => {
            const button = document.createElement('button');
            const angle = choosingHours ? (value % 12) * 30 : (value / 5) * 30;
            button.type = 'button';
            button.className = 'clock-time-number';
            button.style.setProperty('--clock-angle', `${angle}deg`);
            button.textContent = choosingHours ? String(value) : String(value).padStart(2, '0');
            button.setAttribute('aria-label', choosingHours ? `${value} o'clock` : `${String(value).padStart(2, '0')} minutes`);
            button.setAttribute('aria-pressed', String(value === selectedValue));
            button.addEventListener('click', () => {
                if (choosingHours) {
                    selectedHour = value;
                    clockMode = 'minutes';
                } else {
                    selectedMinute = value;
                }
                updateClockReadout();
                renderClockFace();
            });
            clockTimeNumbers.append(button);
        });
    };
    const alignClockPanel = () => {
        const fieldBounds = clockTimePicker.getBoundingClientRect();
        const panelWidth = Math.min(320, window.innerWidth - 32);
        const panelHeight = clockTimePanel.getBoundingClientRect().height;
        const left = Math.max(16, Math.min(fieldBounds.left, window.innerWidth - panelWidth - 16));
        const spaceBelow = window.innerHeight - fieldBounds.bottom - 16;
        const maxTop = Math.max(16, window.innerHeight - panelHeight - 16);
        const preferredTop = spaceBelow >= panelHeight + 6 ? fieldBounds.bottom + 6 : fieldBounds.top - panelHeight - 6;
        clockTimePanel.style.left = `${left}px`;
        clockTimePanel.style.top = `${Math.max(16, Math.min(preferredTop, maxTop))}px`;
    };
    const closeClockPanel = (returnFocus = false) => {
        clockTimePanel.hidden = true;
        clockTimeToggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) clockTimeToggle.focus();
    };
    const openClockPanel = () => {
        const otherPanel = document.getElementById('package-picker-panel');
        const otherToggle = document.getElementById('package-picker-toggle');
        if (otherPanel) otherPanel.hidden = true;
        otherToggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('package-picker-open');
        clockTimePanel.hidden = false;
        clockTimeToggle.setAttribute('aria-expanded', 'true');
        alignClockPanel();
        renderClockFace();
        document.querySelector(`.clock-time-number[aria-pressed="true"]`)?.focus();
    };

    if (timeInput.value) {
        const [hours, minutes] = timeInput.value.split(':').map(Number);
        selectedHour = hours % 12 || 12;
        selectedMinute = minutes;
        selectedPeriod = hours < 12 ? 'AM' : 'PM';
        clockTimeValue.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
    }
    updateClockReadout();
    renderClockFace();
    clockTimeToggle.addEventListener('click', () => {
        if (clockTimePanel.hidden) openClockPanel();
        else closeClockPanel();
    });
    selectedHourOutput.addEventListener('click', () => { clockMode = 'hours'; renderClockFace(); });
    selectedMinuteOutput.addEventListener('click', () => { clockMode = 'minutes'; renderClockFace(); });
    document.querySelectorAll('[data-clock-period]').forEach((button) => button.addEventListener('click', () => {
        selectedPeriod = button.dataset.clockPeriod;
        updateClockReadout();
    }));
    document.getElementById('clock-time-cancel').addEventListener('click', () => closeClockPanel(true));
    document.getElementById('clock-time-apply').addEventListener('click', () => {
        const hours24 = (selectedHour % 12) + (selectedPeriod === 'PM' ? 12 : 0);
        timeInput.value = `${String(hours24).padStart(2, '0')}:${String(selectedMinute).padStart(2, '0')}`;
        timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        clockTimeValue.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
        clockTimeToggle.removeAttribute('aria-invalid');
        document.getElementById('clock-time-error').hidden = true;
        closeClockPanel(true);
    });
    document.addEventListener('pointerdown', (event) => {
        if (!clockTimePicker.contains(event.target)) closeClockPanel();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !clockTimePanel.hidden) closeClockPanel(true);
    });
    window.addEventListener('resize', () => { if (!clockTimePanel.hidden) alignClockPanel(); });
    window.addEventListener('scroll', () => { if (!clockTimePanel.hidden) alignClockPanel(); }, true);
    document.getElementById('reservation-form').addEventListener('submit', (event) => {
        if (timeInput.value) return;

        event.preventDefault();
        clockTimeToggle.setAttribute('aria-invalid', 'true');
        document.getElementById('clock-time-error').hidden = false;
        clockTimeToggle.focus();
    });
}

const updateBudgetEstimate = () => {
    const selectedPackage = packageInput?.selectedOptions[0];
    const guestCount = Number(guestCountInput?.value);

    if (!selectedPackage?.value || !guestCount) {
        budgetOutput.textContent = 'Choose a package and guest count to see an estimate.';
        return;
    }

    budgetOutput.textContent = pesoFormatter.format(Number(selectedPackage.dataset.price) * guestCount);
};
packageInput?.addEventListener('change', updateBudgetEstimate);
guestCountInput?.addEventListener('input', updateBudgetEstimate);
updateBudgetEstimate();
</script>
@endsection
