@extends('layouts.app')

@section('title', 'Check Reservation Status | 3YOS Catering')

@section('content')
<section class="status-page">
    <div class="container py-5 py-lg-6">
        <div class="status-card form-card">
            <div class="status-header">
                <span class="eyebrow">Reservation status</span>
                <h1 class="mt-2 mb-2">Check your booking</h1>
                <p class="text-muted mb-0">Enter the unique reservation ID you received after submitting your request.</p>
            </div>

            <form method="GET" action="{{ route('reservation.status') }}" class="status-search">
                <label for="reservation-code">Reservation ID</label>
                <div class="input-group input-group-lg">
                    <input id="reservation-code" type="text" name="code" value="{{ old('code', $code ?? '') }}" class="form-control" placeholder="e.g. RES-ABCD1234" required>
                    <button type="submit" class="btn btn-primary">Check status</button>
                </div>
            </form>

            @if($reservation)
                <div class="reservation-timeline {{ $reservation->status === 'cancelled' ? 'reservation-timeline--cancelled' : '' }}">
                    @foreach($reservation->timelineSteps() as $step)
                        <div class="timeline-step timeline-step--{{ $step['state'] }}">
                            <span class="timeline-step-marker" aria-hidden="true">
                                @if($step['state'] === 'complete') &#10003;
                                @elseif($step['state'] === 'current') &#9679;
                                @elseif($step['state'] === 'cancelled') &#10007;
                                @endif
                            </span>
                            <div class="timeline-step-body">
                                <div class="timeline-step-label">{{ $step['label'] }}</div>
                                <p class="timeline-step-desc">{{ $step['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="status-section-heading">Reservation details</div>
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
    .py-lg-6{padding-top:5rem!important;padding-bottom:5rem!important}.status-page{background:linear-gradient(135deg,#f8f3eb,#f4e7d8)}.status-card{max-width:980px;margin:0 auto;padding:clamp(1.35rem,4vw,3rem);border:1px solid var(--line);box-shadow:0 20px 45px rgba(70,42,24,.08)}.status-header{padding-bottom:2rem;border-bottom:1px solid var(--line)}.status-header h1{font-size:clamp(2.3rem,4vw,4rem);line-height:1.02}.status-header p{max-width:520px;line-height:1.65}.status-search{padding:1.8rem 0}.status-search label{display:block;margin-bottom:.45rem;color:var(--muted);font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.status-search .form-control{border-color:var(--line)}.status-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.status-detail{min-height:86px;padding:1rem;background:var(--paper)}.status-detail span{display:block;margin-bottom:.35rem;color:var(--muted);font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.status-detail strong{display:block;overflow-wrap:anywhere}.status-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--line)}
    .reservation-timeline{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;margin:.5rem 0 2rem}
    .timeline-step{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;padding:0 .4rem}
    .timeline-step-marker{position:relative;z-index:1;display:grid;place-items:center;width:32px;height:32px;flex:0 0 auto;border-radius:50%;border:2px solid var(--line);background:var(--paper);color:#b7b0a4;font-weight:800;font-size:.95rem;line-height:1}
    .timeline-step:not(:last-child):before{content:'';position:absolute;top:15px;left:calc(50% + 16px);width:calc(100% - 32px);height:2px;background:var(--line);z-index:0}
    .timeline-step--complete .timeline-step-marker{background:var(--wine);border-color:var(--wine);color:#fff}
    .timeline-step--complete:not(:last-child):before{background:var(--wine)}
    .timeline-step--current .timeline-step-marker{border-color:var(--wine);color:var(--wine);background:var(--paper);box-shadow:0 0 0 4px rgba(109,48,36,.14)}
    .timeline-step--cancelled .timeline-step-marker{background:#a73838;border-color:#a73838;color:#fff}
    .timeline-step-label{margin-top:.6rem;font-weight:800;font-size:.8rem;color:var(--ink)}
    .timeline-step-desc{margin-top:.3rem;font-size:.72rem;line-height:1.45;color:var(--muted);max-width:170px}
    .timeline-step--upcoming .timeline-step-label{color:var(--muted)}
    .timeline-step--upcoming .timeline-step-desc{opacity:.75}
    .timeline-step--current .timeline-step-label{color:var(--wine)}
    .status-section-heading{margin:0 0 .9rem;color:var(--muted);font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
    body.dark-mode .status-page{background:linear-gradient(135deg,#1c1815,#29221d)}body.dark-mode .status-detail{background:var(--paper)}
    body.dark-mode .timeline-step-marker{background:#201f1d}
    @media(max-width:767px){.py-lg-6{padding-top:3rem!important;padding-bottom:3rem!important}.status-details{grid-template-columns:repeat(2,minmax(0,1fr))}.status-footer{align-items:flex-start;flex-direction:column}.status-footer .btn{width:100%}.status-search .input-group{display:flex;flex-direction:column;gap:.65rem}.status-search .input-group>*{width:100%;border-radius:8px!important}
    .reservation-timeline{display:flex;flex-direction:column;margin:.5rem 0 2rem}
    .timeline-step{flex-direction:row;align-items:flex-start;text-align:left;padding:0 0 1.35rem;gap:.85rem}
    .timeline-step:last-child{padding-bottom:0}
    .timeline-step:not(:last-child):before{top:32px;left:15px;width:2px;height:calc(100% - 18px)}
    .timeline-step-desc{max-width:none}}
</style>
@endsection
