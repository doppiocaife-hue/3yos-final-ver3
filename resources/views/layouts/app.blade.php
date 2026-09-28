<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '3YOS Catering | Exceptional celebrations')</title>
    <link rel="icon" type="image/png" href="{{ request()->getBaseUrl() }}/images/logo-transparent.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#20201d;--muted:#6f6d66;--cream:#f8f6f1;--paper:#fffdf9;--wine:#6d3024;--terracotta:#b66545;--gold:#c7984b;--line:#e7e1d7}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;overflow-x:hidden}
        body{margin:0;padding:0;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--cream)}
        h1,h2,h3,h4,h5,.display-font{font-family:'Playfair Display',Georgia,serif}
        a{text-decoration:none}
        .container,.container-fluid{padding-left:clamp(1rem,2vw,2rem);padding-right:clamp(1rem,2vw,2rem)}
        .row{margin-left:0;margin-right:0}
        .navbar{position:fixed;top:0;left:0;right:0;z-index:1035;width:100%;margin:0;background:rgba(255,253,249,.94)!important;border-bottom:1px solid rgba(32,32,29,.07);backdrop-filter:blur(14px);transition:transform .28s ease, box-shadow .28s ease;transform:translateY(0)}
        .navbar.header-hidden{transform:translateY(-120%)}
        .navbar-brand{display:inline-flex;align-items:center;gap:.75rem;color:var(--wine)!important;font-family:'Playfair Display',Georgia,serif;font-size:1.4rem;font-weight:800;letter-spacing:.02em}
        .navbar-brand-text{display:flex;flex-direction:column;line-height:1.05}
        .navbar-brand-text small{font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);font-family:'DM Sans',sans-serif;font-weight:700}
        .nav-link{color:var(--ink)!important;font-size:.9rem;font-weight:600;padding:.8rem .85rem!important}
        .nav-link:hover,.nav-link.active{color:var(--terracotta)!important}
        .btn{border-radius:999px;font-weight:700;font-size:.88rem;letter-spacing:.02em;padding:.76rem 1.35rem;transition:.2s ease}
        .btn-primary{background:var(--wine);border-color:var(--wine)}
        .btn-primary:hover,.btn-primary:focus{background:#512218;border-color:#512218;transform:translateY(-2px)}
        .btn-outline-primary{color:var(--wine);border-color:var(--wine)}
        .btn-outline-primary:hover{background:var(--wine);border-color:var(--wine)}
        .eyebrow{color:var(--terracotta);font-size:.75rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        .theme-toggle{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);background:transparent;color:var(--ink);padding:.48rem .7rem;font-size:.78rem;font-weight:700;border-radius:999px;min-width:42px;min-height:42px;line-height:1}
        .footer{margin-top:3rem;background:#211b18;color:#f6f0e8;padding-top:2rem;padding-bottom:1.25rem}
        .footer a{color:#f6f0e8;text-decoration:none}
        .footer a:hover{color:#e7b77a}
        .footer-title{font-family:'Playfair Display',Georgia,serif;font-size:1.55rem}
        .footer-kicker{color:#d9a45f;font-size:.68rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase}
        .footer-heading{color:#fffaf3;font-size:.76rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        .footer-links{display:grid;gap:.58rem;font-size:.9rem}
        .footer-contact{display:flex;align-items:center;gap:.7rem;padding:.78rem 0;border-top:1px solid rgba(255,255,255,.12)}
        .footer-contact-icon{display:grid;place-items:center;flex:0 0 30px;width:30px;height:30px;border:1px solid rgba(231,183,122,.45);border-radius:50%;color:#e7b77a;font-size:.68rem;font-weight:800}
        .footer-contact small{display:block;color:#c8bdb3;font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .footer-facebook{display:inline-flex;align-items:center;gap:.6rem;padding:.7rem .9rem;border:1px solid rgba(231,183,122,.6);color:#fff8ef!important;font-size:.82rem;font-weight:700;transition:.2s ease;border-radius:999px}
        .footer-facebook:hover{background:#b66545;border-color:#b66545;color:#fff!important;transform:translateY(-2px)}
        body.dark-mode{--ink:#f5f1e9;--muted:#c9c3b9;--cream:#151515;--paper:#201f1d;--line:#575148;--wine:#e6ad92;--terracotta:#edb18f;--gold:#e7bd72;background:var(--cream);color:var(--ink)}
        body.dark-mode .navbar{background:rgba(27,26,24,.95)!important;border-color:#403c35}
        body.dark-mode .navbar-brand,body.dark-mode .nav-link,body.dark-mode .theme-toggle{color:#f5f1e9!important}
        body.dark-mode .theme-toggle{background:#2a2724;border-color:#575148}
        body.dark-mode .hero{background:linear-gradient(115deg,#1b1714 0%,#2a241f 55%,#221d1a 100%)!important}
        body.dark-mode .hero-copy{color:#d8d0c5!important}
        body.dark-mode main,body.dark-mode .bg-paper,body.dark-mode .section-heading,body.dark-mode .card,body.dark-mode .form-card,body.dark-mode .service-tile,body.dark-mode .process-card,body.dark-mode .cta-panel,body.dark-mode .occasion-panel,body.dark-mode .metric-card,body.dark-mode .mini-cta,body.dark-mode .reservation-sidebar,body.dark-mode .reservation-tile,body.dark-mode .process-item{background:#201f1d!important;color:#f5f1e9!important}
        body.dark-mode .card p,body.dark-mode .card h1,body.dark-mode .card h2,body.dark-mode .card h3,body.dark-mode .card h4,body.dark-mode .card h5,body.dark-mode .process-item strong,body.dark-mode .process-item p,body.dark-mode .service-tile h3,body.dark-mode .service-tile p,body.dark-mode .mini-cta h3,body.dark-mode .mini-cta p,body.dark-mode .reservation-checklist li,body.dark-mode .reservation-tile p,body.dark-mode .form-label,body.dark-mode .text-muted,body.dark-mode .eyebrow{color:#f5f1e9!important}
        body.dark-mode .form-control,body.dark-mode .form-select{background:#151515;border-color:#555047;color:#f5f1e9}
        body.dark-mode .form-control::placeholder,body.dark-mode textarea::placeholder{color:#aebbc8;opacity:1}
        body.dark-mode .form-select{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dce7f0' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px}
        body.dark-mode input[type="date"]{color-scheme:dark}
        body.dark-mode input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.82;cursor:pointer}
        body.dark-mode .form-text,body.dark-mode .date-availability{color:var(--muted)!important}
        body.dark-mode .date-availability.text-success{color:#8fe3aa!important}
        body.dark-mode .date-availability.text-danger{color:#ff9b9b!important}
        body.dark-mode .page-kicker{color:#75d8cf}
        body.dark-mode .btn-outline-primary{color:#f5f1e9;border-color:#d9b18f;background:transparent}
        body.dark-mode .btn-outline-primary:hover{background:#d9b18f;color:#1b1714}
        body.dark-mode .btn-primary{background:#d79c6a;border-color:#d79c6a;color:#1b1714}
        body.dark-mode .footer{background:#0f0d0c}
        body.dark-mode .footer a,body.dark-mode .footer p,body.dark-mode .footer small,body.dark-mode .footer span{color:#f3ebdf!important}
        body.dark-mode .footer-contact-icon{border-color:rgba(231,183,122,.8);color:#e7b77a}
        body.dark-mode .card,body.dark-mode .process-item{border-color:#403b36!important}
        body.dark-mode .navbar-toggler-icon{filter:invert(1)}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top py-2">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png" alt="3YOS Catering Services" style="height:48px;width:auto;object-fit:contain;border-radius:50%;background:transparent">
                <span class="navbar-brand-text">
                    <span>3YOS</span>
                    <small>Catering</small>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('services') ? 'active' : '' }}" href="{{ route('services') }}">Services</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('packages*') ? 'active' : '' }}" href="{{ route('packages') }}">Packages</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('gallery') ? 'active' : '' }}" href="{{ route('gallery') }}">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reservation.status') ? 'active' : '' }}" href="{{ route('reservation.status') }}">Status</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('inquiry') ? 'active' : '' }}" href="{{ route('inquiry') }}">Inquiry</a></li>
                    <li class="nav-item ms-lg-2"><button type="button" class="theme-toggle" id="customerThemeToggle" aria-label="Enable dark mode" title="Enable dark mode"><span aria-hidden="true">&#9790;</span></button></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="{{ route('reservation') }}">Book an event</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>@yield('content')</main>

    <footer class="footer">
        <div class="container">
            <div class="row g-5 pb-5">
                <div class="col-lg-4">
                    <div class="footer-kicker mb-2">Catering & party needs</div>
                    <div class="footer-title">3YOS Catering</div>
                    <p class="text-white-50 mt-3 mb-0">Thoughtful food, graceful styling, and dependable service for celebrations that deserve to feel effortless.</p>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="footer-heading mb-3">Explore</div>
                    <div class="footer-links">
                        <a href="{{ route('home') }}">Home</a>
                        <a href="{{ route('services') }}">Services</a>
                        <a href="{{ route('packages') }}">Packages</a>
                        <a href="{{ route('reservation') }}">Book now</a>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="footer-heading mb-3">Get in touch</div>
                    <div class="footer-contact">
                        <span class="footer-contact-icon">AD</span>
                        <div>
                            <small>Address</small>
                            <span>Marikina City, Metro Manila</span>
                        </div>
                    </div>
                    <div class="footer-contact">
                        <span class="footer-contact-icon">FB</span>
                        <div>
                            <small>Facebook</small>
                            <a href="https://www.facebook.com/profile.php?id=100063690915629" target="_blank" rel="noopener noreferrer">Message 3YOS Catering</a>
                        </div>
                    </div>
                    <div class="footer-contact">
                        <span class="footer-contact-icon">IN</span>
                        <div>
                            <small>Inquiry</small>
                            <a href="{{ route('inquiry') }}">Tell us about your event</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="footer-heading mb-3">Start planning</div>
                    <p class="text-white-50 small mb-3">Share your date, venue, guest count, and celebration vision. We’ll help you build the right package.</p>
                    <a href="{{ route('reservation') }}" class="footer-facebook">Book an event <span>→</span></a>
                </div>
            </div>
            <div class="border-top border-secondary pt-4 d-flex flex-column flex-md-row justify-content-between gap-2 small text-white-50">
                <span>&copy; {{ date('Y') }} 3YOS Catering Services & Party Needs. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const button = document.getElementById('customerThemeToggle');
            if (!button) return;
            const applyTheme = (dark) => {
                document.body.classList.toggle('dark-mode', dark);
                button.innerHTML = dark ? '&#9788;' : '&#9790;';
                button.setAttribute('aria-label', dark ? 'Enable light mode' : 'Enable dark mode');
                button.setAttribute('title', dark ? 'Enable light mode' : 'Enable dark mode');
                button.setAttribute('aria-pressed', String(dark));
            };
            applyTheme(localStorage.getItem('customer-theme') === 'dark');
            button.addEventListener('click', () => {
                const dark = !document.body.classList.contains('dark-mode');
                localStorage.setItem('customer-theme', dark ? 'dark' : 'light');
                applyTheme(dark);
            });
        })();
    </script>
    <script>
        (() => {
            const header = document.querySelector('.navbar');
            if (!header) return;

            let previousScrollY = window.scrollY;
            const threshold = 12;

            const showHeader = () => header.classList.remove('header-hidden');
            const hideHeader = () => header.classList.add('header-hidden');

            const handleScroll = () => {
                const currentScrollY = window.scrollY;

                if (currentScrollY <= 0) {
                    showHeader();
                    previousScrollY = currentScrollY;
                    return;
                }

                if (Math.abs(currentScrollY - previousScrollY) < threshold) {
                    return;
                }

                if (currentScrollY > previousScrollY) {
                    hideHeader();
                } else {
                    showHeader();
                }

                previousScrollY = currentScrollY;
            };

            window.addEventListener('scroll', handleScroll, { passive: true });
            handleScroll();
        })();
    </script>
    <script>
        document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea').forEach((field) => {
            if (field.title) return;
            const label = field.id ? Array.from(field.form?.querySelectorAll('label') || []).find((item) => item.htmlFor === field.id) : null;
            const fieldName = (label?.textContent || field.placeholder || field.name || 'this field').trim().replace(/\s+/g, ' ').toLowerCase();
            const instruction = field.type === 'email' ? 'Enter a valid email address.'
                : field.type === 'tel' ? 'Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces.'
                    : field.type === 'date' ? 'Choose the event date.'
                        : field.type === 'time' ? 'Choose the event time.'
                            : field.type === 'file' ? 'Choose a file that meets the accepted format and size.'
                                : field instanceof HTMLSelectElement ? `Choose ${fieldName}.`
                                    : field instanceof HTMLTextAreaElement ? `Describe ${fieldName}.`
                                        : `Enter ${fieldName}.`;
            field.title = instruction;
        });
    </script>
</body>
</html>
