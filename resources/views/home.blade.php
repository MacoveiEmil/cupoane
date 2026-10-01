@extends('layouts.app')

@section('content')
{{-- Sus: titlul, un paragraf scurt (editabil din admin) și căutarea; ofertele încep imediat dedesubt. --}}
<section class="intro">
    <div class="wrap">
        <div class="intro__in">
            <div>
                <h1 class="intro__title">{{ $site['hero_title'] }}</h1>
                {{-- Cifrele apar doar de la 100 de oferte; sub prag ar transmite că site-ul e mic. --}}
                <p class="intro__meta">@if ($stats['offers'] >= 100)<b>{{ \App\Support\Seo::plural($stats['offers'], 'ofertă activă', 'oferte active') }}</b> la <b>{{ \App\Support\Seo::plural($stats['stores'], 'magazin', 'magazine') }}</b> · actualizat azi, {{ \App\Support\Day::dayMonth() }}@else Actualizat azi, {{ \App\Support\Day::dayMonth() }}@endif</p>
                @if (trim($site['hero_subtitle']) !== '')<p class="intro__text">{{ $site['hero_subtitle'] }}</p>@endif
            </div>
            @include('partials.search', ['big' => true])
        </div>
    </div>
</section>

<div class="wrap">
    {{-- 1. Prospețimea: cine caută reduceri vrea întâi codurile noi. 2. Cele mai mari reduceri. 3. Magazinele. --}}
    @foreach ([['s-new', 'Cele mai noi vouchere', $recent, route('top').'#recente'], ['s-top', 'Top coduri și oferte', $top, route('top')]] as [$id, $title, $list, $more])
        @if ($list->isNotEmpty())
            <section class="sec" aria-labelledby="{{ $id }}">
                <div class="sec__head"><h2 id="{{ $id }}">{{ $title }}</h2><a class="more" href="{{ $more }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
                {{-- 4 căsuțe mici: 2 × 2 pe mobil, un rând de 4 pe ecrane mai late. --}}
                <div class="tiles">@foreach ($list as $c) @include('partials.coupon-tile', ['c' => $c]) @endforeach</div>
            </section>
        @endif
    @endforeach

    @if ($popular->isNotEmpty())
        <section class="sec" aria-labelledby="s-pop">
            <div class="sec__head"><h2 id="s-pop">Magazine populare</h2><a class="more" href="{{ route('stores') }}">Toate magazinele <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <ul class="brands brands--tiles">@foreach ($popular as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
        </section>
    @endif

    @if ($exclusive->isNotEmpty())
        <section class="sec" aria-labelledby="s-ex">
            <div class="sec__head"><h2 id="s-ex">Coduri exclusive</h2><a class="more" href="{{ route('top') }}#exclusive">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <div class="cards">@foreach ($exclusive as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
        </section>
    @endif

    @if ($deals->isNotEmpty())
        <section class="sec" aria-labelledby="s-deals">
            <div class="sec__head"><h2 id="s-deals">Deal-uri</h2><a class="more" href="{{ route('deals') }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <div class="deals">@foreach ($deals as $d) @include('partials.deal', ['d' => $d]) @endforeach</div>
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section class="sec" aria-labelledby="s-cat">
            <div class="sec__head"><h2 id="s-cat">Categorii populare</h2><a class="more" href="{{ route('categories') }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <ul class="cats">
                @foreach ($categories as $row)
                    <li><a href="{{ route('category', $row['category']) }}">{{ $row['category']->name }}</a><span>{{ \App\Support\Seo::plural($row['stores'], 'magazin', 'magazine') }} · {{ \App\Support\Seo::plural($row['offers'], 'ofertă', 'oferte') }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (trim(strip_tags($site['home_seo_text'])) !== '')
        <section class="sec prose seo">{!! $site['home_seo_text'] !!}</section>
    @endif

    @include('partials.faq', ['faq' => $faq, 'title' => $faqTitle])
</div>
@endsection
