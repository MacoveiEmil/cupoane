<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $seo->fullTitle() }}</title>
@if ($seo->description !== '')<meta name="description" content="{{ $seo->description }}">@endif

<link rel="canonical" href="{{ $seo->canonical }}">
<meta name="robots" content="{{ $seo->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">
<meta property="og:locale" content="ro_RO">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->fullTitle() }}">
@if ($seo->description !== '')<meta property="og:description" content="{{ $seo->description }}">@endif

<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta name="theme-color" content="#fff6ea">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>{!! \App\Support\Assets::css() !!}</style>
<script>document.documentElement.classList.add('js')</script>
<script type="application/ld+json">{!! json_encode($seo->schema(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script src="{{ \App\Support\Assets::js() }}" defer></script>
</head>
<body>
@include('partials.icons')
<a class="skip" href="#main">Sari la conținut</a>

<header class="hdr">
    <div class="wrap hdr__in">
        <a class="logo-mark" href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>
            <span class="logo-mark__icon" aria-hidden="true"><svg width="22" height="22"><use href="#i-ticket"/></svg></span>
            <span>{{ config('app.name') }}</span>
        </a>
        <nav class="nav nav--d" aria-label="Principal">
            @foreach ($menu as [$label, $url])
                <a href="{{ $url }}" @if (url()->current() === $url) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @unless (request()->is('/'))
            <div class="hdr__search">@include('partials.search', ['big' => false])</div>
        @endunless
        <details class="nav nav--m" data-menu>
            <summary aria-label="Meniu"><svg class="i-menu" width="22" height="22"><use href="#i-menu"/></svg><svg class="i-x" width="22" height="22"><use href="#i-x"/></svg></summary>
            <div class="nav__panel">
                @foreach ($menu as [$label, $url])
                    <a href="{{ $url }}">{{ $label }}</a>
                @endforeach
                @unless (request()->is('/'))
                    @include('partials.search', ['big' => false])
                @endunless
            </div>
        </details>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="ftr">
    <div class="wrap ftr__grid">
        <div class="ftr__about">
            <a class="logo-mark logo-mark--light" href="{{ url('/') }}"><span class="logo-mark__icon" aria-hidden="true"><svg width="22" height="22"><use href="#i-ticket"/></svg></span><span>{{ config('app.name') }}</span></a>
            <p>{{ $site['footer_about'] }}</p>
            <p class="ftr__note">{{ $site['affiliate_note'] }}</p>
        </div>
        <nav aria-labelledby="f1">
            <h2 id="f1">Descoperă</h2>
            <ul>@foreach ($menu as [$label, $url])<li><a href="{{ $url }}">{{ $label }}</a></li>@endforeach</ul>
        </nav>
        <nav aria-labelledby="f2">
            <h2 id="f2">Informații</h2>
            <ul>@foreach ($footerPages as $p)<li><a href="{{ url('/'.$p->slug) }}">{{ $p->title }}</a></li>@endforeach</ul>
        </nav>
    </div>
    <div class="wrap ftr__bottom">© {{ now()->year }} {{ config('app.name') }} · Vouchere, reduceri și cupoane verificate</div>
</footer>
</body>
</html>
