@extends('layouts.admin')

@section('content')
<div class="page-header">
    <div><div class="page-kicker">Business snapshot</div><h1 class="fw-bold">Good day, admin.</h1><p class="text-muted mb-0">Here is a live overview of your catering operations.</p></div>
    <a class="btn luxury-btn" href="{{ route('admin.reservations') }}">Review reservations</a>
</div>

@php($attentionTotal = array_sum($needsAttention))
<section class="card mb-4 attention-card">
    <div class="panel-header"><div><h2 class="h5 fw-bold mb-1">Needs attention</h2><p class="text-muted small mb-0">Open items that need a decision.</p></div>@if($attentionTotal > 0)<span class="badge-soft badge-soft--warn">{{ $attentionTotal }} open</span>@endif</div>
    @if($attentionTotal === 0)
        <p class="text-muted small mb-0">Nothing needs attention right now.</p>
    @else
        <div class="attention-list">
            @if($needsAttention['pending_reservations'] > 0)
                <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'pending']) }}"><span class="attention-item-label">Pending reservations need a decision</span><span class="attention-item-count">{{ $needsAttention['pending_reservations'] }}</span></a>
            @endif
            @if($needsAttention['inquiries_needing_response'] > 0)
                <a class="attention-item" href="{{ route('admin.inquiries') }}"><span class="attention-item-label">Inquiries need a response</span><span class="attention-item-count">{{ $needsAttention['inquiries_needing_response'] }}</span></a>
            @endif
            @if($needsAttention['unpaid_accepted'] > 0)
                <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::NO_PAYMENT]) }}"><span class="attention-item-label">Accepted reservations with no payment on file</span><span class="attention-item-count">{{ $needsAttention['unpaid_accepted'] }}</span></a>
            @endif
            @if($needsAttention['missing_contracts'] > 0)
                <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::MISSING_CONTRACT]) }}"><span class="attention-item-label">Accepted reservations missing a contract</span><span class="attention-item-count">{{ $needsAttention['missing_contracts'] }}</span></a>
            @endif
            @if($needsAttention['outstanding_balances'] > 0)
                <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::OUTSTANDING_BALANCE]) }}"><span class="attention-item-label">Accepted reservations with an outstanding balance</span><span class="attention-item-count">{{ $needsAttention['outstanding_balances'] }}</span></a>
            @endif
        </div>
    @endif
</section>

<section class="card mb-4">
    <div class="panel-header"><div><h2 class="h5 fw-bold mb-1">Today</h2><p class="text-muted small mb-0">What’s on the schedule this week — not lifetime totals.</p></div></div>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <a class="today-tile today-tile-link" href="{{ route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}">
                <span>Events today</span><strong>{{ $todaySection['events_today'] }}</strong><em>View schedule &rarr;</em>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="today-tile today-tile-link" href="{{ route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->addDay()->toDateString(), 'date_to' => now()->addDays(7)->toDateString()]) }}">
                <span>Upcoming (next 7 days)</span><strong>{{ $todaySection['upcoming_events'] }}</strong><em>View schedule &rarr;</em>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="today-tile today-tile-link" href="{{ route('admin.reservations', ['payment_due' => 'soon']) }}">
                <span>Payments due soon</span><strong>{{ $todaySection['payments_due'] }}</strong><em>View reservations &rarr;</em>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="today-tile today-tile-link" href="{{ route('admin.inquiries', ['view' => 'needs_attention']) }}">
                <span>Inquiries needing response</span><strong>{{ $todaySection['inquiries_needing_response'] }}</strong><em>View inquiries &rarr;</em>
            </a>
        </div>
    </div>
</section>

<div class="panel-header mb-2"><div><h2 class="h5 fw-bold mb-1">Business overview</h2><p class="text-muted small mb-0">Lifetime totals — not today’s activity.</p></div></div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.reservations') }}"><span class="badge-soft">Bookings</span><h3>{{ $reservationCount }}</h3><p class="mb-0 text-muted">Total reservation requests</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.inquiries') }}"><span class="badge-soft">Inbox</span><h3>{{ $inquiryCount }}</h3><p class="mb-0 text-muted">Customer inquiries received</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.reservations', ['status' => 'completed']) }}"><span class="badge-soft">Completed</span><h3>{{ $businessOverview['completed_events'] }}</h3><p class="mb-0 text-muted">Events completed</p></a></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="badge-soft">Revenue</span><h3>&#8369;{{ number_format($businessOverview['revenue'], 0) }}</h3><p class="mb-0 text-muted">Net payments received, all time</p></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><span class="badge-soft">Outstanding</span><h3>&#8369;{{ number_format($businessOverview['outstanding_balance'], 0) }}</h3><p class="mb-0 text-muted">Unpaid balance across all bookings</p></div></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.services.index') }}"><span class="badge-soft">Services</span><h3>{{ $serviceCount }}</h3><p class="mb-0 text-muted">Active service offerings</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ session('admin_role') === 'full' ? route('admin.packages.index') : route('packages') }}"><span class="badge-soft">Packages</span><h3>{{ $packageCount }}</h3><p class="mb-0 text-muted">Published catering packages</p></a></div>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="panel-header"><div><h5 class="fw-bold mb-1">Priority workspace</h5><p class="text-muted small mb-0">Keep client communication and booking decisions moving.</p></div><span class="badge-soft">Today</span></div><div class="workflow-item"><div class="workflow-icon">01</div><div><strong>Review reservation requests</strong><p class="text-muted mb-0">Confirm availability, update status, and respond to event needs.</p></div><a href="{{ route('admin.reservations') }}">Open</a></div><div class="workflow-item"><div class="workflow-icon">02</div><div><strong>Reply to inquiries</strong><p class="text-muted mb-0">Give prospective clients a timely, helpful response.</p></div><a href="{{ route('admin.inquiries') }}">Open</a></div>@if(session('admin_role') === 'full')<div class="workflow-item"><div class="workflow-icon">03</div><div><strong>Keep packages current</strong><p class="text-muted mb-0">Update inclusions, pricing, and featured offerings.</p></div><a href="{{ route('admin.packages.index') }}">Manage</a></div>@endif</div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="panel-header"><div><h5 class="fw-bold mb-1">Quick actions</h5><p class="text-muted small mb-0">Frequently used management tools.</p></div></div><div class="d-grid gap-2"><a class="quick-link" href="{{ route('admin.inquiries') }}"><span>Client inquiries</span><b>→</b></a><a class="quick-link" href="{{ route('admin.reservations') }}"><span>Reservations</span><b>→</b></a>@if(session('admin_role') === 'full')<a class="quick-link" href="{{ route('admin.packages.index') }}"><span>Package editor</span><b>→</b></a><a class="quick-link" href="{{ route('admin.analytics') }}"><span>Business analytics</span><b>→</b></a>@endif</div></div></div>
</div>
<section class="calendar-card card mt-4" id="reservation-calendar" aria-labelledby="reservation-calendar-title">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <h5 class="fw-bold mb-1" id="reservation-calendar-title">Reservation calendar</h5>
            <p class="text-muted small mb-0">Pending, accepted, completed, and cancelled events by date.</p>
            <p class="small mb-0"><a href="{{ route('admin.support') }}#category-admin-calendar">How do I use the calendar?</a></p>
        </div>
        <div class="calendar-legend" aria-label="Reservation status legend">
            <span><i class="calendar-dot calendar-dot--pending"></i>Pending</span>
            <span><i class="calendar-dot calendar-dot--confirmed"></i>Accepted</span>
            <span><i class="calendar-dot calendar-dot--completed"></i>Completed</span>
            <span><i class="calendar-dot calendar-dot--cancelled"></i>Cancelled</span>
        </div>
    </div>
    <div class="calendar-toolbar">
        <button type="button" class="calendar-nav" id="calendarPrevious" aria-label="Previous month">&#8592;</button>
        <h6 id="calendarMonth" class="mb-0 fw-bold" aria-live="polite"></h6>
        <button type="button" class="calendar-nav" id="calendarNext" aria-label="Next month">&#8594;</button>
    </div>
    <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
    <div class="calendar-grid" id="reservationCalendar" aria-label="Calendar days"></div>
    <div class="calendar-events" id="calendarEvents" aria-live="polite"></div>
</section>
<style>
.attention-card .panel-header{align-items:center}
.attention-list{display:grid;gap:.6rem}
.attention-item{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--ink);text-decoration:none;transition:border-color .15s ease,background .15s ease}
.attention-item:hover{border-color:var(--teal);background:var(--mint);color:var(--ink)}
.attention-item-label{font-size:.85rem;font-weight:600}
.attention-item-count{display:inline-flex;min-width:26px;height:26px;align-items:center;justify-content:center;padding:0 .5rem;border-radius:999px;background:#fff5d8;color:#714d00;font-weight:800;font-size:.8rem}
body.dark-mode .attention-item-count{background:rgba(146,99,0,.28);color:#f7d57a}
.today-tile{display:block;height:100%;padding:.9rem 1rem;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:inherit;text-decoration:none;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}
.today-tile span{display:block;margin-bottom:.35rem;color:var(--muted);font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.today-tile strong{display:block;font-family:Manrope,sans-serif;font-size:1.5rem;font-weight:800;color:var(--navy)}
body.dark-mode .today-tile strong{color:#f1f6f8}
.today-tile-link{cursor:pointer}
.today-tile-link em{display:block;margin-top:.4rem;color:var(--teal-dark);font-size:.72rem;font-weight:700;font-style:normal}
.today-tile-link:hover,.today-tile-link:focus-visible{transform:translateY(-2px);box-shadow:0 14px 30px rgba(30,50,62,.1);border-color:var(--teal);color:inherit}
.today-tile-link:focus-visible{outline:3px solid rgba(34,130,121,.35);outline-offset:2px}
.calendar-card{overflow:visible}
.calendar-legend{display:flex;align-items:center;flex-wrap:wrap;gap:.9rem;color:var(--muted);font-size:.72rem;font-weight:700}
.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}
.calendar-dot{width:9px;height:9px;border-radius:50%;background:var(--teal)}
.calendar-dot--pending{background:#d49b28}.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}
.calendar-events{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-top:1rem}
.calendar-event-detail{display:grid;gap:.25rem;padding:.7rem .8rem;border:1px solid var(--line);border-left:3px solid #0d8b83;border-radius:8px;background:var(--surface);font-size:.75rem;overflow-wrap:anywhere}
.calendar-event-detail--pending{border-left-color:#d49b28}.calendar-event-detail--completed{border-left-color:#4d77b8}.calendar-event-detail--cancelled{border-left-color:#c54545}
.calendar-event-detail strong{font-size:.78rem}.calendar-event-detail small{color:var(--muted);line-height:1.35}
.calendar-day:focus-within{outline:2px solid #71c9c0;outline-offset:1px}
.calendar-grid > .calendar-day:nth-child(7n+4) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n+5) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n+6) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n) .calendar-hover-card{right:0;left:auto}
.calendar-hover-card{max-width:min(240px,calc(100vw - 2rem))}
body.dark-mode .calendar-event--cancelled{background:#f9e0e1;border-color:#a73838;color:#4d1316;box-shadow:inset 0 0 0 1px rgba(167,56,56,.2)}
body.dark-mode .calendar-event--cancelled .calendar-event__status{color:#6d1818}
@media(max-width:992px){.calendar-events{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.calendar-events{grid-template-columns:1fr}.calendar-legend{gap:.5rem}}
.calendar-toolbar{display:flex;align-items:center;justify-content:center;gap:1.25rem;margin-bottom:1rem}.calendar-nav{width:34px;height:34px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--teal-dark);font-size:1.1rem;line-height:1}.calendar-nav:hover{background:var(--mint)}.calendar-weekdays,.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}.calendar-weekdays{color:var(--muted);font-size:.68rem;font-weight:800;letter-spacing:.08em;text-align:center;text-transform:uppercase;margin-bottom:6px}.calendar-day{position:relative;min-height:92px;padding:.55rem;background:#fbfcfd;border:1px solid var(--line);border-radius:8px}.calendar-day--empty{background:transparent;border-color:transparent}.calendar-day--today{border-color:#71c9c0;box-shadow:inset 0 0 0 1px #71c9c0}.calendar-day-number{font-size:.78rem;font-weight:800}.calendar-event{display:flex;flex-direction:column;gap:.1rem;width:100%;margin-top:.45rem;padding:.28rem .35rem;border:0;border-left:3px solid;border-radius:4px;background:var(--mint);color:var(--ink);font-size:.68rem;text-align:left;line-height:1.2;white-space:normal;overflow:hidden;word-break:break-word}.calendar-event__title{display:block;font-weight:700;line-height:1.2;color:inherit}.calendar-event__status{display:block;font-size:.52rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:inherit;opacity:1}.calendar-event--pending{border-color:#d49b28}.calendar-event--confirmed{border-color:#0d8b83}.calendar-event--completed{border-color:#4d77b8}.calendar-event--cancelled{border-color:#a73838;background:#f9e0e1;color:#4d1316;box-shadow:inset 0 0 0 1px rgba(167,56,56,.2)}.calendar-event--cancelled .calendar-event__status{color:#6d1818}.calendar-hover-card{position:absolute;z-index:20;top:calc(100% + 6px);left:0;width:240px;padding:.7rem;background:var(--surface);border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 28px rgba(21,37,55,.18);opacity:0;pointer-events:none;transform:translateY(-4px);transition:opacity .15s ease,transform .15s ease}.calendar-day:hover .calendar-hover-card,.calendar-day:focus-within .calendar-hover-card{opacity:1;transform:translateY(0)}.calendar-hover-item{padding:.35rem 0;border-top:1px solid var(--line);font-size:.7rem}.calendar-hover-item:first-child{padding-top:0;border-top:0}.calendar-hover-item strong{display:block}.calendar-hover-item small{display:block;color:var(--muted);margin-top:.12rem}.calendar-legend{display:flex;flex-wrap:wrap;gap:.8rem;color:var(--muted);font-size:.72rem;font-weight:700}.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}.calendar-dot{width:8px;height:8px;border-radius:50%;display:inline-block}.calendar-dot--pending{background:#d49b28}.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}.calendar-events{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1rem}.calendar-event-detail{padding:.75rem;border:1px solid var(--line);border-radius:8px;background:var(--surface)}.calendar-event-detail strong{display:block;font-size:.8rem}.calendar-event-detail small{display:block;color:var(--muted);margin-top:.15rem}.calendar-event-detail--pending{border-left:3px solid #d49b28}.calendar-event-detail--cancelled{border-left:3px solid #c54545}.calendar-event-detail--confirmed{border-left:3px solid #0d8b83}.calendar-event-detail--completed{border-left:3px solid #4d77b8}
.calendar-event{cursor:pointer;text-decoration:none;transition:filter .12s ease,box-shadow .12s ease}
.calendar-event:hover,.calendar-event:focus-visible{filter:brightness(.96)}
.calendar-event:focus-visible{outline:2px solid var(--teal-dark);outline-offset:1px}
.calendar-event-detail{color:inherit;text-decoration:none;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease}
.calendar-event-detail:hover,.calendar-event-detail:focus-visible{border-color:var(--teal);box-shadow:0 8px 18px rgba(30,50,62,.08);color:inherit}
.calendar-event-detail:focus-visible{outline:2px solid var(--teal-dark);outline-offset:1px}
body.dark-mode .calendar-day{background:#12202e}.calendar-event-detail{background:var(--surface)}

@media(max-width:992px){
    .calendar-events{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:768px){
    .calendar-day{min-height:72px;padding:.35rem}.calendar-event{font-size:.6rem;padding:.2rem}.calendar-events{grid-template-columns:1fr}.calendar-legend{gap:.5rem}
}

@media(max-width:575px){
    .calendar-weekdays{font-size:.58rem}.calendar-weekdays,.calendar-grid{gap:3px}.calendar-day{min-height:58px;padding:.25rem}.calendar-day-number{font-size:.68rem}.calendar-event{height:auto;margin-top:.3rem;padding:.24rem .28rem;border-left-width:3px;font-size:.6rem}.calendar-event--cancelled{background:#fff1f1;color:#6b1f1f}.calendar-event--cancelled .calendar-event__status{color:#8d2020}.calendar-event--completed{background:#ecf2ff}.calendar-event--confirmed{background:#e7f7f4}
}
</style>
<script>
(() => {
    const reservations = @json($calendarEvents);
    // Reuses the existing reservation detail route — '__ID__' is swapped for each event's real
    // reservation id so every calendar event/list card opens that exact reservation.
    const reservationUrlTemplate = @json(route('admin.reservations.show', ['reservation' => '__ID__']));
    const reservationUrl = (id) => reservationUrlTemplate.replace('__ID__', id);
    const calendar = document.getElementById('reservationCalendar');
    const monthLabel = document.getElementById('calendarMonth');
    const eventsPanel = document.getElementById('calendarEvents');
    if (!calendar || !monthLabel || !eventsPanel) return;

    const today = new Date();
    let displayedMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const formatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
    const statusLabels = { pending: 'Pending', confirmed: 'Accepted', completed: 'Completed', cancelled: 'Cancelled' };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
    const eventsByDate = reservations.reduce((events, reservation) => {
        (events[reservation.date] ??= []).push(reservation);
        return events;
    }, {});

    const renderMonthEvents = () => {
        const monthPrefix = `${displayedMonth.getFullYear()}-${String(displayedMonth.getMonth() + 1).padStart(2, '0')}`;
        const monthEvents = reservations.filter((reservation) => reservation.date.startsWith(monthPrefix));
        eventsPanel.innerHTML = monthEvents.length
            ? monthEvents.map((event) => `<a class="calendar-event-detail calendar-event-detail--${event.status}" href="${reservationUrl(event.id)}"><strong>${escapeHtml(event.eventType)} · ${statusLabels[event.status]}</strong><small>${escapeHtml(event.date)} · ${escapeHtml(event.time)}</small><small>${escapeHtml(event.name)} · ${escapeHtml(event.venue)}</small></a>`).join('')
            : '<p class="text-muted small mb-0">No reservations scheduled this month.</p>';
    };

    const renderCalendar = () => {
        const year = displayedMonth.getFullYear();
        const month = displayedMonth.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        monthLabel.textContent = formatter.format(displayedMonth);
        calendar.replaceChildren();

        for (let index = 0; index < firstDay; index += 1) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day calendar-day--empty';
            emptyDay.setAttribute('aria-hidden', 'true');
            calendar.append(emptyDay);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = eventsByDate[date] ?? [];
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day${date === todayKey ? ' calendar-day--today' : ''}`;
            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = String(day);
            dayElement.append(dayNumber);

            dayEvents.forEach((event) => {
                const eventLink = document.createElement('a');
                eventLink.href = reservationUrl(event.id);
                eventLink.className = `calendar-event calendar-event--${event.status}`;
                eventLink.innerHTML = `
                    <span class="calendar-event__title">${escapeHtml(event.eventType)}</span>
                    ${event.status === 'cancelled' ? '<span class="calendar-event__status">Cancelled</span>' : ''}
                `;
                eventLink.setAttribute('aria-label', `${event.eventType}, ${statusLabels[event.status]}, ${event.time}, ${event.name}, ${event.venue}. Open reservation detail.`);
                dayElement.append(eventLink);
            });

            if (dayEvents.length) {
                const hoverCard = document.createElement('div');
                hoverCard.className = 'calendar-hover-card';
                hoverCard.innerHTML = dayEvents.map((event) => `<div class="calendar-hover-item"><strong>${escapeHtml(event.eventType)} · ${statusLabels[event.status]}</strong><small>${escapeHtml(event.time)} · ${escapeHtml(event.name)}</small><small>${escapeHtml(event.venue)}</small></div>`).join('');
                dayElement.append(hoverCard);
            }

            calendar.append(dayElement);
        }

        renderMonthEvents();
    };

    document.getElementById('calendarPrevious')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() - 1, 1);
        renderCalendar();
    });
    document.getElementById('calendarNext')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1);
        renderCalendar();
    });
    renderCalendar();
})();
</script>
@endsection
