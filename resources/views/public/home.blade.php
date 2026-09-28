@extends('layouts.app')

@section('title', '3YOS Catering | Exceptional celebrations')

@section('content')
<section class="hero">
    <div class="container py-5 py-lg-0">
        <div class="row align-items-center min-vh-75 g-5">
            <div class="col-lg-7 py-lg-5">
                <div class="eyebrow mb-3">Catering · Styling · Celebration</div>
                <h1 class="hero-title mb-4">Beautiful food for life’s important moments.</h1>
                <p class="hero-copy mb-4">From weddings to birthdays and corporate events, we create memorable experiences with great food, warm service, and stress-free planning.</p>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <a href="{{ route('reservation') }}" class="btn btn-primary">Book an event</a>
                    <a href="{{ route('packages') }}" class="btn btn-outline-primary">View packages</a>
                </div>
            </div>
            <div class="col-lg-5">
                @php($availableGalleryImages = $galleryImages->filter(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->image_path))->values())
                @if($availableGalleryImages->isNotEmpty())
                    <div class="hero-art hero-art--slideshow" data-gallery-slideshow>
                        @foreach($availableGalleryImages as $index => $galleryImage)
                            <img
                                src="{{ route('gallery.image', ['path' => $galleryImage->image_path]) }}"
                                alt="{{ $galleryImage->title ?: 'Gallery image' }}"
                                class="hero-art__slide {{ $index === 0 ? 'is-active' : '' }}"
                                loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                data-index="{{ $index }}"
                                onerror="this.style.display='none';"
                            >
                        @endforeach
                        <div class="hero-art__overlay"></div>
                        <div class="hero-art__label">
                            <span>Made for your moment</span>
                            <strong>Menus with heart.<br>Service with ease.</strong>
                        </div>
                    </div>
                @else
                    <div class="hero-art">
                        <div class="hero-art__label">
                            <span>Made for your moment</span>
                            <strong>Menus with heart.<br>Service with ease.</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5 bg-paper">
    <div class="container">
        <div class="row g-4 home-metrics">
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">1500+</div>
                    <div class="metric-label">events hosted</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">12 yrs</div>
                    <div class="metric-label">experience</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">24/7</div>
                    <div class="metric-label">planning support</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">4.9/5</div>
                    <div class="metric-label">client rating</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <div class="eyebrow mb-2">Why clients choose us</div>
            <h2 class="section-title mb-3">Thoughtful service, beautifully executed.</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <article class="service-tile h-100">
                    <div class="service-number">01</div>
                    <h3>Weddings</h3>
                    <p>Elegant catering for your most meaningful day.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="service-tile h-100">
                    <div class="service-number">02</div>
                    <h3>Private events</h3>
                    <p>Birthdays, debuts, and family gatherings with ease.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="service-tile h-100">
                    <div class="service-number">03</div>
                    <h3>Corporate events</h3>
                    <p>Professional service for meetings and company milestones.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5 bg-paper">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="eyebrow mb-2">How it works</div>
                <h2 class="section-title mb-3">A smoother event planning process.</h2>
                <div class="process-list">
                    <div class="process-item">
                        <span>1</span>
                        <div>
                            <strong>Share your vision</strong>
                            <p>Tell us your event type, guest count, budget, and preferred mood.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>2</span>
                        <div>
                            <strong>We recommend a package</strong>
                            <p>We match your needs with a proposal that feels practical and polished.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>3</span>
                        <div>
                            <strong>Enjoy a stress-free event</strong>
                            <p>From setup to service, we handle the details so you can relax.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mini-cta">
                    <div class="eyebrow mb-2">Need a custom touch?</div>
                    <h3>We can build a menu around your event.</h3>
                    <p>Perfect for weddings, milestones, and gatherings that deserve a tailored plan.</p>
                    <a href="{{ route('inquiry') }}" class="btn btn-primary">Send an inquiry</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5">
    <div class="container">
        <div class="cta-panel text-center">
            <div class="eyebrow mb-2">Ready to plan?</div>
            <h2 class="section-title mb-3">Let’s make your next event feel beautifully easy.</h2>
            <p class="mb-4">Tell us about your celebration and we’ll help you build the right package.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="{{ route('reservation') }}" class="btn btn-primary">Book an event</a>
                <a href="{{ route('inquiry') }}" class="btn btn-outline-primary">Send an inquiry</a>
            </div>
        </div>
    </div>
</section>

<style>
    .min-vh-75{min-height:68vh}
    .hero{background:linear-gradient(115deg,#f5eee3 0%,#fbf8f2 60%,#e8d9c6 100%);padding-top:calc(74px + clamp(2.25rem,4vw,4.25rem))}
    .hero-title{font-size:clamp(2.8rem,5vw,4.8rem);line-height:.98;letter-spacing:-.045em;max-width:700px}
    .hero-copy{color:#625e57;font-size:1.08rem;line-height:1.7;max-width:560px}
    .hero-art{min-height:390px;position:relative;overflow:hidden;background:radial-gradient(circle at 40% 25%,#e8bd78 0 10%,transparent 10.5%),radial-gradient(circle at 70% 75%,#ac5e3f 0 17%,transparent 17.5%),linear-gradient(145deg,#7c3d2d,#d38a5e);box-shadow:18px 18px 0 #ded2c0;border-radius:26px}
    .hero-art:before,.hero-art:after{content:'';position:absolute;border:1px solid rgba(255,255,255,.45);border-radius:50%}
    .hero-art:before{width:315px;height:315px;top:35px;left:48px}
    .hero-art:after{width:210px;height:210px;bottom:-65px;right:-35px}
    .hero-art--slideshow{background:#3a2d26}
    .hero-art__slide{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1s ease-in-out}
    .hero-art__slide.is-active{opacity:1}
    .hero-art__overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(32,24,20,.18),rgba(18,14,12,.46));z-index:1}
    .hero-art__label{position:absolute;z-index:2;bottom:28px;left:28px;color:#fff;max-width:78%}
    .hero-art__label span{display:block;font-size:.7rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;margin-bottom:.45rem}
    .hero-art__label strong{font-family:'Playfair Display',serif;font-size:1.65rem;line-height:1.1}
    .home-metrics{margin-top:-1.25rem}
    .metric-card{background:#f9f1e8;border:1px solid rgba(109,48,36,.08);border-radius:18px;padding:1rem 1rem .9rem;text-align:center;box-shadow:0 10px 28px rgba(32,32,29,.04)}
    .metric-value{font-weight:800;font-size:clamp(1.5rem,2vw,2.1rem);color:var(--wine);font-family:'Playfair Display',Georgia,serif}
    .metric-label{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-top:.25rem}
    .service-tile{padding:2rem;border:1px solid rgba(109,48,36,.08);background:#f9f2ea;border-radius:22px;box-shadow:0 10px 28px rgba(109,48,36,.04)}
    .service-number{font-size:.75rem;font-weight:800;color:var(--terracotta);letter-spacing:.14em;margin-bottom:2rem}
    .service-tile h3{font-size:1.5rem}
    .service-tile p{color:var(--muted);line-height:1.65;margin-bottom:0}
    .process-list{display:grid;gap:1rem}
    .process-item{display:flex;gap:1rem;padding:1rem 1.1rem;border:1px solid rgba(109,48,36,.08);border-radius:18px;background:#f8efe6}
    .process-item span{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:var(--wine);color:#fff;font-weight:800}
    .process-item strong{display:block;margin-bottom:.3rem}
    .process-item p{margin:0;color:var(--muted);line-height:1.6}
    .mini-cta{padding:2rem;border-radius:24px;background:linear-gradient(145deg,#f3e7d9,#f8f0ea);border:1px solid rgba(109,48,36,.08)}
    .mini-cta h3{font-size:clamp(1.7rem,2vw,2.4rem);line-height:1.1;margin-bottom:.75rem}
    .mini-cta p{color:var(--muted);line-height:1.7;margin-bottom:1.25rem}
    .occasion-panel{padding:clamp(2rem,4vw,4rem);background:#ece3d5}
    .check-list{list-style:none;padding:0}
    .check-list li{padding:1rem 0;border-bottom:1px solid rgba(109,48,36,.16);font-weight:700}
    .check-list li:last-child{border-bottom:0}
    .check-list span{display:inline-grid;place-items:center;width:22px;height:22px;margin-right:.75rem;border-radius:50%;background:rgba(109,48,36,.08);color:var(--wine);font-size:.75rem}
    .section-title{font-size:clamp(2.1rem,3.4vw,3rem);line-height:1.08;letter-spacing:-.03em}
    .cta-panel{padding:2.5rem 1.5rem;border:1px solid rgba(109,48,36,.08);background:linear-gradient(140deg,#f9f3ed,#f4e7d8);border-radius:22px}
    @media(max-width:575px){.hero-art{min-height:280px}.hero-title{font-size:2.5rem}.cta-panel{padding:2rem 1rem}.process-item{padding:.85rem .9rem}.metric-card{padding:.8rem .7rem}}
</style>

@if($galleryImages->count() > 1)
<script>
    (() => {
        const slideshow = document.querySelector('[data-gallery-slideshow]');
        if (!slideshow) return;

        const slides = Array.from(slideshow.querySelectorAll('.hero-art__slide'));
        if (slides.length < 2) return;

        let currentIndex = 0;
        const showSlide = (index) => {
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
        };

        setInterval(() => {
            currentIndex = (currentIndex + 1) % slides.length;
            showSlide(currentIndex);
        }, 4500);
    })();
</script>
@endif
@endsection

