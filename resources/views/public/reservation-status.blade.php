@extends('layouts.app')

@section('title', 'Check Reservation Status | 3YOS Catering')

@section('content')
<section class="status-page">
    <div class="container py-5 py-lg-6">
        <div class="status-card form-card">
            <div class="status-header">
                <div>
                    <span class="eyebrow">Reservation status</span>
                    <h1 class="mt-2 mb-2">Check your booking</h1>
                    <p class="text-muted mb-0">Enter the unique reservation ID you received after submitting your request.</p>
                </div>
                @if($reservation)
                    <div class="status-panel status-panel--{{ $reservation->status }}">
                        <div class="status-panel-label">Current status</div>
                        <div class="status-panel-value">{{ str_replace('_', ' ', $reservation->status) }}</div>
                        <div class="status-panel-id">{{ $reservation->reservation_code }}</div>
                    </div>
                @endif
            </div>

            <form method="GET" action="{{ route('reservation.status') }}" class="status-search">
                <label for="reservation-code">Reservation ID</label>
                <div class="input-group input-group-lg">
                    <input id="reservation-code" type="text" name="code" value="{{ old('code', $code ?? '') }}" class="form-control" placeholder="e.g. RES-ABCD1234" required>
                    <button type="submit" class="btn btn-primary">Check status</button>
                </div>
            </form>

            @if($reservation)
                <div class="status-details">
                    <div class="status-detail"><span>Client</span><strong>{{ $reservation->full_name }}</strong></div>
                    <div class="status-detail"><span>Reservation ID</span><strong>{{ $reservation->reservation_code }}</strong></div>
                    <div class="status-detail"><span>Event date</span><strong>{{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</strong></div>
                    <div class="status-detail"><span>Event type</span><strong>{{ $reservation->event_type }}</strong></div>
                    <div class="status-detail"><span>Venue</span><strong>{{ $reservation->venue }}</strong></div>
                    <div class="status-detail"><span>Guests</span><strong>{{ number_format($reservation->guest_count) }}</strong></div>
                </div>
            @elseif($code !== '')
                <div class="alert alert-warning mb-0">We could not find a reservation with that ID. Please check the code and try again.</div>
            @endif

            <div class="status-footer">
                <p class="text-muted mb-0">Need to make a new booking?</p>
                <a href="{{ route('reservation') }}" class="btn btn-outline-primary">Back to reservation form</a>
            </div>
        </div>
    </div>
</section>
<style>
    .py-lg-6{padding-top:5rem!important;padding-bottom:5rem!important}.status-page{background:linear-gradient(135deg,#f8f3eb,#f4e7d8)}.status-card{max-width:980px;margin:0 auto;padding:clamp(1.35rem,4vw,3rem);border:1px solid var(--line);box-shadow:0 20px 45px rgba(70,42,24,.08)}.status-header{display:flex;align-items:flex-start;justify-content:space-between;gap:2rem;padding-bottom:2rem;border-bottom:1px solid var(--line)}.status-header h1{font-size:clamp(2.3rem,4vw,4rem);line-height:1.02}.status-header p{max-width:520px;line-height:1.65}.status-panel{min-width:170px;padding:1rem 1.1rem;background:#dcefe8;border:1px solid #b8d9cb;color:#245c4d}.status-panel--pending{background:#fff2d7;border-color:#ecd29d;color:#785512}.status-panel--cancelled{background:#fae1df;border-color:#edbdb8;color:#8f3029}.status-panel--completed{background:#e1e9f8;border-color:#bac9e2;color:#35527e}.status-panel-label{font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.status-panel-value{margin-top:.35rem;font-family:'Playfair Display',Georgia,serif;font-size:1.35rem;font-weight:700;text-transform:capitalize}.status-panel-id{margin-top:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.04em}.status-search{padding:1.8rem 0}.status-search label{display:block;margin-bottom:.45rem;color:var(--muted);font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.status-search .form-control{border-color:var(--line)}.status-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.status-detail{min-height:86px;padding:1rem;background:var(--paper)}.status-detail span{display:block;margin-bottom:.35rem;color:var(--muted);font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.status-detail strong{display:block;overflow-wrap:anywhere}.status-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--line)}
    body.dark-mode .status-page{background:linear-gradient(135deg,#1c1815,#29221d)}body.dark-mode .status-detail{background:var(--paper)}
    @media(max-width:767px){.py-lg-6{padding-top:3rem!important;padding-bottom:3rem!important}.status-header{display:block}.status-panel{margin-top:1.5rem}.status-details{grid-template-columns:repeat(2,minmax(0,1fr))}.status-footer{align-items:flex-start;flex-direction:column}.status-footer .btn{width:100%}.status-search .input-group{display:flex;flex-direction:column;gap:.65rem}.status-search .input-group>*{width:100%;border-radius:8px!important}}
</style>
@endsection
