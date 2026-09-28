<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3YOS Operations</title>
    <link rel="icon" type="image/png" href="{{ request()->getBaseUrl() }}/images/logo-transparent.png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0f2438;--navy-2:#173a58;--teal:#0d8b83;--teal-dark:#087168;--mint:#e8f7f5;--ink:#152537;--muted:#677789;--canvas:#f4f7fa;--surface:#fff;--line:#e4eaf0;--danger:#c54545;--shadow:0 12px 30px rgba(21,37,55,.07)}
        *{box-sizing:border-box} html,body{margin:0;padding:0;overflow-x:hidden} body{margin:0;padding:0;font-family:"DM Sans",sans-serif;background:var(--canvas);color:var(--ink);font-size:.93rem}.container-fluid{padding-left:0;padding-right:0}.row{margin-left:0;margin-right:0}.admin-layout{display:flex;min-height:100vh;width:100%;max-width:100%;align-items:stretch}.admin-main{display:flex;flex-direction:column;flex:1 1 auto;min-width:0;min-height:100vh}.sidebar{background:linear-gradient(165deg,#f8fbfd,#e6eef3);box-shadow:10px 0 34px rgba(15,36,56,.1);width:230px;max-width:230px;flex:0 0 230px;position:sticky;top:0;height:100vh;align-self:flex-start;overflow:hidden}.brand{padding:1.15rem 1rem .9rem;border-bottom:1px solid rgba(21,37,55,.12)}.brand-mark{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:#26aaa0;color:#fff;font-family:Manrope,sans-serif;font-weight:800;letter-spacing:-.1em}.brand h4{font-family:Manrope,sans-serif;letter-spacing:-.04em;font-size:1.05rem;color:#152537;line-height:1.15;margin:0}.brand-subtitle{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:#5c7282;margin-top:.18rem}
        .sidebar nav{padding:.7rem .7rem .9rem}.nav-caption{margin:.25rem .65rem .35rem;color:#607787;font-size:.66rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase}.sidebar .nav-link{position:relative;display:flex;align-items:center;justify-content:flex-start;margin:.08rem 0;padding:.62rem .8rem;border-radius:10px;color:#30495b;font-weight:600;transition:.18s ease;min-height:38px;line-height:1.25}.sidebar .nav-link:hover{background:#d9f1ee;color:#087168}.sidebar .nav-link.active{background:#c9ebe7;color:#075c56;box-shadow:inset 3px 0 #0d8b83}.sidebar-icon{display:none;width:0;height:0;margin:0;padding:0}
        .header-bar{position:sticky;top:0;z-index:1020;min-height:76px;margin:0;background:rgba(255,255,255,.88);backdrop-filter:blur(14px);border-bottom:1px solid var(--line);width:100%}.page-kicker{color:var(--teal);font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;font-weight:800}.admin-heading h4{font-family:Manrope,sans-serif;font-size:1.05rem;letter-spacing:-.03em}.header-btn{border:1px solid var(--line);background:#fff;color:#46576a;border-radius:9px;font-size:.8rem;font-weight:700;padding:.55rem .75rem}.header-btn:hover{border-color:#9bcfc9;color:var(--teal-dark);background:var(--mint)}
        .main-admin-panel{display:flex;flex-direction:column;min-height:100vh;min-width:0;flex:1 1 auto}.admin-page-content{width:100%}
        .content-card,.card{background:var(--surface);border:1px solid var(--line);border-radius:15px;box-shadow:var(--shadow)}.content-card{padding:1.6rem!important}.content-card>div>h1,.content-card h1{font-family:Manrope,sans-serif;letter-spacing:-.045em;font-size:1.6rem}.text-muted{color:var(--muted)!important}.stat-card{height:100%;padding:1.35rem!important;background:var(--surface);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);transition:.18s ease}.stat-card:hover,.card:hover{transform:translateY(-2px);box-shadow:0 17px 34px rgba(21,37,55,.1)}.badge-soft{display:inline-block;padding:.3rem .58rem;background:var(--mint);border-radius:6px;color:var(--teal-dark);font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.stat-card h3{font-family:Manrope,sans-serif;font-size:2rem;letter-spacing:-.06em;margin:.7rem 0 .2rem}.luxury-btn{border:0;border-radius:9px;background:var(--teal);color:#fff;font-weight:700;box-shadow:0 5px 12px rgba(13,139,131,.2)}.luxury-btn:hover,.luxury-btn:focus{background:var(--teal-dark);color:#fff}.btn{border-radius:8px;font-weight:700}.btn-outline-secondary{border-color:#ccd7e1;color:#4c6073}.btn-outline-secondary:hover{background:#eef6f6;border-color:#a4d5cf;color:var(--teal-dark)}
        .table{--bs-table-color:var(--ink);--bs-table-bg:transparent;--bs-table-hover-bg:#f5fbfb;--bs-table-hover-color:var(--ink);margin-bottom:0}.table thead{background:#f7f9fb;color:#627387;font-size:.69rem;letter-spacing:.08em;text-transform:uppercase}.table th{padding:.85rem 1rem;border-bottom:1px solid var(--line);white-space:nowrap}.table td{padding:1rem;border-color:var(--line);vertical-align:middle}.form-control,.form-select{border:1px solid #d9e2ea;border-radius:8px;padding:.6rem .75rem;color:var(--ink)}.form-control:focus,.form-select:focus{border-color:#3cb8ad;box-shadow:0 0 0 .2rem rgba(13,139,131,.12)}.alert{border:0;border-radius:10px;font-weight:600}.alert-success{background:#e7f7ee;color:#17633d}.alert-danger{background:#fff0f0;color:#9d3333}
        body.dark-mode{--canvas:#101b27;--surface:#172635;--ink:#edf5fb;--muted:#b4c2ce;--line:#2c4153;--mint:#153f42;background:var(--canvas)}body.dark-mode .sidebar{background:linear-gradient(165deg,#102c45,#0b1c2d);box-shadow:10px 0 34px rgba(0,0,0,.28)}body.dark-mode .brand{border-color:rgba(255,255,255,.11)}body.dark-mode .brand h4{color:#edf5fb}body.dark-mode .brand-subtitle{color:#89a5b9}body.dark-mode .nav-caption{color:#6f8da3}body.dark-mode .sidebar .nav-link{color:#a9bfce}body.dark-mode .sidebar .nav-link:hover{background:rgba(117,216,207,.1);color:#f3fbff}body.dark-mode .sidebar .nav-link.active{background:#123b4b;color:#fff;box-shadow:inset 3px 0 #75d8cf}body.dark-mode .sidebar-icon{color:#75d8cf}body.dark-mode .header-bar{background:rgba(16,27,39,.94)}body.dark-mode .header-btn{background:#172635;border-color:var(--line);color:#edf5fb}body.dark-mode main :is(h1,h2,h3,h4,h5,h6,p,span,td,th,label,small,strong,li){color:var(--ink)}body.dark-mode main .text-muted{color:var(--muted)!important}body.dark-mode main a:not(.btn){color:#75d8cf}body.dark-mode .table{--bs-table-color:var(--ink);--bs-table-bg:transparent;--bs-table-hover-bg:#1d3343;--bs-table-hover-color:var(--ink)}body.dark-mode .table thead{background:#1d3343;color:#d8e7f0}body.dark-mode .table thead th{color:#d8e7f0}body.dark-mode .form-control,body.dark-mode .form-select{background:#12202e;border-color:#3a5163;color:var(--ink)}body.dark-mode .form-control::placeholder{color:#8ca0b2}body.dark-mode .form-select option{background:#12202e;color:var(--ink)}body.dark-mode .dropdown-menu{background:#172635;border-color:var(--line)}body.dark-mode .dropdown-item{color:var(--ink)}body.dark-mode .btn-outline-secondary{color:#e4eff6;border-color:#527087}body.dark-mode .btn-outline-danger{color:#ffb7b7;border-color:#a25c62}body.dark-mode [style*="background:#f8ede3"]{background:#1d3343!important}
        @media(max-width:991px){.admin-layout{display:block;min-height:auto}.sidebar{position:fixed;inset:0 auto 0 0;z-index:1050;width:min(82vw,260px);max-width:min(82vw,260px);flex-basis:min(82vw,260px);min-height:100vh!important;height:100vh;overflow:hidden;transform:translateX(-105%);transition:transform .22s ease}.mobile-admin-nav-open .sidebar{transform:translateX(0);overflow:hidden}.admin-nav-backdrop{display:none;position:fixed;inset:0;z-index:1040;background:rgba(4,15,25,.6)}.mobile-admin-nav-open .admin-nav-backdrop{display:block}.admin-main{min-height:auto}.header-bar{position:static}.content-card{padding:1.2rem!important}.stat-card h3{font-size:1.5rem}.table td,.table th{padding:.6rem}.stat-card{padding:1rem!important}}
        @media(max-width:768px){.admin-header-actions{flex-wrap:wrap}.header-bar{flex-direction:column!important;align-items:flex-start!important}.admin-heading{width:100%}.admin-header-actions{width:100%;gap:1rem!important}.admin-header-actions>*{flex:1;min-width:120px}.content-card h1{font-size:1.3rem}.table td,.table th{font-size:.85rem;padding:.5rem}.table th{padding:.6rem .5rem}.quick-link{flex-direction:column;align-items:flex-start!important}.quick-link b{align-self:flex-end;margin-top:.5rem}.workflow-item a{margin-left:0;margin-top:.5rem;align-self:flex-start}.row.g-3,.row.g-4{gap:.75rem!important}.col-lg-7,.col-lg-5{margin-bottom:0!important}.card{padding:.75rem!important}.card h5{font-size:1rem}}
        @media(max-width:575px){body{font-size:.9rem}.content-card{border-radius:12px;padding:1rem!important}.admin-header-actions{display:flex!important;flex-direction:column;width:100%;gap:1rem}.admin-header-actions>*{width:100%}.admin-header-actions .btn{width:100%;min-height:39px}.content-card h1{font-size:1.2rem}.table td,.table th{padding:.4rem;font-size:.8rem}.btn{min-height:40px;font-size:.85rem}.header-btn{padding:.45rem .6rem;font-size:.75rem}.stat-card{padding:.75rem!important}.stat-card h3{font-size:1.2rem;margin:.5rem 0 .1rem}.badge-soft{font-size:.6rem;padding:.2rem .45rem}.d-flex{flex-wrap:wrap}.justify-content-between{justify-content:space-between!important}.gap-2{gap:.5rem!important}.gap-3{gap:.75rem!important}.gap-4{gap:1rem!important}.p-4{padding:1rem!important}.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}.table{font-size:.8rem;margin-bottom:0}.table td{word-break:break-word}.table th{white-space:normal;padding:.5rem .3rem}.text-end{text-align:left!important}.quick-link{flex-direction:column;align-items:flex-start;padding:.6rem}.quick-link b{margin-top:.4rem;align-self:flex-end}.workflow-item{flex-direction:column;align-items:flex-start;gap:.5rem;padding:.6rem 0}.workflow-item strong{font-size:.85rem}.workflow-item a{width:100%;text-align:center;margin:0;padding:.3rem}.workflow-item p{font-size:.75rem;margin-top:.1rem}.workflow-icon{width:30px;height:30px;font-size:.65rem}.card p{font-size:.85rem;margin-bottom:.5rem}.form-control,.form-select{font-size:.9rem;padding:.5rem}.form-label{font-size:.9rem}.btn-group{flex-direction:column;width:100%}.btn-group .btn{width:100%}}.small-screen-only{display:none}@media(max-width:575px){.small-screen-only{display:inline-block}}
        body.dark-mode .form-select{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dce7f0' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px}
        body.dark-mode input[type="date"]{color-scheme:dark}
        body.dark-mode input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.82;cursor:pointer}
        body.dark-mode .calendar-day-number{color:var(--ink)}
        body.dark-mode .page-kicker{color:#75d8cf}
    </style>
</head>
<body>
<div class="admin-nav-backdrop" id="adminNavBackdrop"></div>
<div class="container-fluid"><div class="admin-layout">
    <aside class="sidebar text-white p-0"><div class="brand d-flex align-items-center gap-3"><img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png" alt="3YOS Catering Services" style="width:42px;height:42px;object-fit:contain;border-radius:50%;background:transparent"><div><h4>3YOS</h4><div class="brand-subtitle">Operations</div></div></div><nav>
        <div class="nav-caption">Workspace</div>
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Overview</a>
        <a class="nav-link {{ request()->routeIs('admin.reservations') ? 'active' : '' }}" href="{{ route('admin.reservations') }}">Reservations</a>
        <a class="nav-link {{ request()->routeIs('admin.inquiries*') ? 'active' : '' }}" href="{{ route('admin.inquiries') }}">Inquiries</a>
        @if(session('admin_role') === 'full')
        <div class="nav-caption mt-3">Content & insights</div>
        <a class="nav-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}" href="{{ route('admin.packages.index') }}">Packages</a>
        <a class="nav-link {{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}">Services</a>
        <a class="nav-link {{ request()->routeIs('admin.gallery.*') ? 'active' : '' }}" href="{{ route('admin.gallery.index') }}">Gallery</a>
        <a class="nav-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}" href="{{ route('admin.analytics') }}">Analytics</a>
        <a class="nav-link {{ request()->routeIs('admin.reports') ? 'active' : '' }}" href="{{ route('admin.reports') }}">Reports</a>
        <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">Team admins</a>
        <a class="nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}" href="{{ route('admin.activity-logs') }}">Activity logs</a>
        @endif
        <div class="nav-caption mt-3">System</div><a class="nav-link {{ request()->routeIs('admin.backups') ? 'active' : '' }}" href="{{ route('admin.backups') }}">Backups</a>
    </nav></aside>
    <div class="admin-main"><header class="header-bar p-3 px-lg-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3"><div class="admin-heading d-flex align-items-center gap-2"><button class="header-btn d-lg-none" type="button" id="adminMobileMenu" aria-expanded="false">Menu</button><div><div class="page-kicker">Catering management</div><h4 class="mb-0">Operations workspace</h4></div></div><div class="admin-header-actions d-flex gap-2 align-items-center"><button class="header-btn theme-toggle" id="themeToggle" type="button" aria-label="Enable dark mode" title="Enable dark mode"><span aria-hidden="true">&#9790;</span></button><a href="{{ route('home') }}" class="header-btn text-center text-decoration-none">View website</a><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-outline-danger btn-sm">Sign out</button></form></div></header><div class="admin-page-content p-3 p-lg-4">@yield('content')</div></div>
</div></div>
<dialog id="admin-password-dialog" aria-labelledby="admin-password-title" style="width:min(440px,calc(100vw - 2rem));padding:1.35rem;border:1px solid var(--line);border-radius:12px;color:var(--ink);background:var(--surface);box-shadow:0 18px 48px rgba(0,0,0,.24)">
    <h2 id="admin-password-title" class="h5 mb-2">Confirm administrator password</h2>
    <p id="admin-password-message" class="text-muted mb-3"></p>
    <form id="admin-password-dialog-form">
        <label for="admin-password-input" class="form-label">Administrator password</label>
        <input id="admin-password-input" type="password" class="form-control" autocomplete="current-password" required>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" id="admin-password-cancel" class="btn btn-outline-secondary">Cancel</button>
            <button type="submit" class="btn luxury-btn">Continue</button>
        </div>
    </form>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>const mobileMenu=document.getElementById('adminMobileMenu'),navBackdrop=document.getElementById('adminNavBackdrop'),closeMobileMenu=()=>{document.body.classList.remove('mobile-admin-nav-open');mobileMenu?.setAttribute('aria-expanded','false')};mobileMenu?.addEventListener('click',()=>{const isOpen=document.body.classList.toggle('mobile-admin-nav-open');mobileMenu.setAttribute('aria-expanded',String(isOpen))});navBackdrop?.addEventListener('click',closeMobileMenu);document.querySelectorAll('.sidebar .nav-link').forEach(link=>link.addEventListener('click',closeMobileMenu));const themeToggle=document.getElementById('themeToggle'),savedTheme=localStorage.getItem('admin-theme'),applyAdminTheme=dark=>{document.body.classList.toggle('dark-mode',dark);themeToggle.innerHTML=dark?'&#9788;':'&#9790;';themeToggle.setAttribute('aria-label',dark?'Enable light mode':'Enable dark mode');themeToggle.setAttribute('title',dark?'Enable light mode':'Enable dark mode');themeToggle.setAttribute('aria-pressed',String(dark))};applyAdminTheme(savedTheme==='dark');themeToggle?.addEventListener('click',()=>{applyAdminTheme(!document.body.classList.contains('dark-mode'));localStorage.setItem('admin-theme',document.body.classList.contains('dark-mode')?'dark':'light')});</script>
<script>
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.hasAttribute('onsubmit') || form.hasAttribute('data-password-confirm')) return;

    const status = form.querySelector('select[name="status"]')?.value || form.querySelector('input[name="status"]')?.value;
    const label = event.submitter?.textContent.trim().toLowerCase() || '';
    let message = form.dataset.confirmMessage || '';

    if (!message && (form.hasAttribute('data-confirm-status') || status === 'cancelled' || label === 'cancel' || (status === 'confirmed' && label === 'accept'))) {
        message = status === 'cancelled'
            ? 'Cancel this reservation? The change will be saved immediately.'
            : status === 'confirmed'
                ? 'Accept this reservation? The change will be saved immediately.'
                : 'Update this reservation status?';
    }
    if (!message && /^(add|create|upload)\b/.test(label)) message = 'Add this item with the details entered?';
    if (!message && /^(save|update)\b/.test(label)) message = 'Update this item with the changes entered?';

    if (message && !window.confirm(message)) event.preventDefault();
});
document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea').forEach((field) => {
    if (field.title) return;
    const label = field.id ? Array.from(field.form?.querySelectorAll('label') || []).find((item) => item.htmlFor === field.id) : null;
    const fieldName = (label?.textContent || field.placeholder || field.name || 'this field').trim().replace(/\s+/g, ' ').toLowerCase();
    const instruction = field.type === 'email' ? 'Enter a valid email address.'
        : field.type === 'tel' ? 'Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces.'
            : field.type === 'date' ? 'Choose a date.'
            : field.type === 'time' ? 'Choose a time.'
                : field.type === 'file' ? 'Choose a file that meets the accepted format and size.'
                    : field instanceof HTMLSelectElement ? `Choose ${fieldName}.`
                        : field instanceof HTMLTextAreaElement ? `Describe ${fieldName}.`
                            : field.type === 'password' ? 'Enter your password.'
                                : `Enter ${fieldName}.`;
    field.title = instruction;
});
</script>
<script>
(() => {
    const dialog = document.getElementById('admin-password-dialog');
    const dialogForm = document.getElementById('admin-password-dialog-form');
    const passwordInput = document.getElementById('admin-password-input');
    const passwordMessage = document.getElementById('admin-password-message');
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-password-confirm')) return;
        if (form.dataset.passwordConfirmed === 'true') {
            delete form.dataset.passwordConfirmed;
            return;
        }

        event.preventDefault();
        pendingForm = form;
        pendingSubmitter = event.submitter;
        passwordMessage.textContent = form.dataset.passwordMessage || 'Confirm your administrator password to continue.';
        passwordInput.value = '';
        dialog.showModal();
        passwordInput.focus();
    });

    document.getElementById('admin-password-cancel').addEventListener('click', () => dialog.close('cancel'));
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });

    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!dialogForm.reportValidity() || !pendingForm) return;

        const form = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;

        const confirmation = document.createElement('input');
        confirmation.type = 'hidden';
        confirmation.name = 'password_confirmation';
        confirmation.value = passwordInput.value;
        form.append(confirmation);
        form.dataset.passwordConfirmed = 'true';
        dialog.close('confirmed');

        if (submitter instanceof HTMLElement && submitter.form === form) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    });
})();
</script>
<script>
(() => {
    const forms = [...document.querySelectorAll('form[data-live-filter]')];
    if (forms.length === 0) return;

    const timers = new WeakMap();
    const requests = new WeakMap();

    const formUrl = (form) => {
        const url = new URL(form.action || window.location.href, window.location.href);
        url.search = '';
        for (const [key, value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') url.searchParams.set(key, value.trim());
        }
        return url;
    };

    const syncFormFromUrl = (form, url) => {
        for (const field of form.elements) {
            if (!field.name || field.type === 'hidden' || field.type === 'submit') continue;
            const value = url.searchParams.get(field.name) || '';
            if (field.type === 'checkbox' || field.type === 'radio') field.checked = value !== '' && field.value === value;
            else field.value = value;
        }
    };

    const refreshResults = async (form, url, historyMode = 'replace') => {
        const selector = form.dataset.liveFilterTarget;
        const currentTarget = document.querySelector(selector);
        if (!currentTarget) return;

        requests.get(form)?.abort();
        const controller = new AbortController();
        requests.set(form, controller);
        currentTarget.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Filter request failed (${response.status}).`);

            const html = await response.text();
            const responseDocument = new DOMParser().parseFromString(html, 'text/html');
            const replacement = responseDocument.querySelector(selector);
            if (!replacement) throw new Error('The filtered results could not be found in the response.');

            const countSelector = form.dataset.liveFilterCount;
            if (countSelector) {
                const nextCount = responseDocument.querySelector(countSelector);
                const currentCount = document.querySelector(countSelector);
                if (nextCount && currentCount) currentCount.textContent = nextCount.textContent;
            }

            document.querySelector(selector)?.replaceWith(replacement);
            if (historyMode === 'push') window.history.pushState({ liveFilter: true }, '', url.href);
            else if (historyMode === 'replace') window.history.replaceState({ liveFilter: true }, '', url.href);
        } catch (error) {
            if (error.name !== 'AbortError') console.error(error);
        } finally {
            document.querySelector(selector)?.removeAttribute('aria-busy');
        }
    };

    const applyFormFilters = (form, historyMode = 'replace') => refreshResults(form, formUrl(form), historyMode);
    const scheduleFormFilters = (form) => {
        clearTimeout(timers.get(form));
        timers.set(form, setTimeout(() => applyFormFilters(form), 350));
    };

    forms.forEach((form) => {
        form.addEventListener('input', () => scheduleFormFilters(form));
        form.addEventListener('change', () => {
            clearTimeout(timers.get(form));
            applyFormFilters(form);
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            clearTimeout(timers.get(form));
            applyFormFilters(form);
        });
    });

    document.addEventListener('click', (event) => {
        const clearLink = event.target.closest('[data-live-filter-clear]');
        if (clearLink) {
            event.preventDefault();
            const form = document.querySelector(clearLink.dataset.liveFilterClear);
            if (!form) return;
            form.querySelectorAll('input:not([type="hidden"])').forEach((input) => { input.value = ''; });
            form.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
            clearTimeout(timers.get(form));
            applyFormFilters(form);
            return;
        }

        const link = event.target.closest('a');
        if (!link || !link.closest('.pagination')) return;
        const form = forms.find((candidate) => document.querySelector(candidate.dataset.liveFilterTarget)?.contains(link));
        if (!form) return;

        event.preventDefault();
        refreshResults(form, new URL(link.href, window.location.href), 'push');
    });

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        forms.forEach((form) => {
            const formPath = new URL(form.action || window.location.href, window.location.href).pathname;
            if (formPath !== url.pathname) return;
            syncFormFromUrl(form, url);
            refreshResults(form, url, 'none');
        });
    });
})();
</script>
</body></html>
