@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')

    <header class="store">
        <span class="store__logo">
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="{{ $store->name }}" width="200" height="80" fetchpriority="high" decoding="async">
            @else
                <span class="brand__ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
            @endif
        </span>
        <div class="store__main">
            <h1>{{ $h1 }}</h1>
            <p class="store__meta">@if ($coupons->isNotEmpty())<span><b>{{ \App\Support\Seo::plural($coupons->count(), 'ofertă activă', 'oferte active') }}</b></span>@endif@foreach ($store->categories as $cat)<a href="{{ route('category', $cat) }}">{{ $cat->name }}</a>@endforeach</p>
        </div>
        <a class="btn btn--go store__go" href="{{ $store->goUrl() }}" target="_blank" rel="sponsored nofollow noopener">Mergi la magazin <svg width="16" height="16"><use href="#i-out"/></svg></a>
    </header>

    {{-- Ofertele (H3) vin direct sub H1, fără titlu intermediar. --}}
    <section class="sec" aria-label="Oferte {{ $store->name }}">
        @if ($coupons->isNotEmpty())
            <div class="cards">@foreach ($coupons as $c) @include('partials.coupon', ['c' => $c, 'showStore' => false]) @endforeach</div>
        @else
            <div class="empty">
                <p><b>Momentan nu avem coduri active pentru {{ $store->name }}.</b></p>
                <p>Ofertele expirate sunt scoase automat, așa că aici apar doar coduri valabile. Revino curând sau încearcă un magazin similar.</p>
                @if ($similar->isNotEmpty())
                    <ul class="brands empty__similar">@foreach ($similar->take(4) as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
                @endif
            </div>
        @endif
        <p class="trust"><svg width="14" height="14"><use href="#i-check"/></svg><a href="{{ url('/cum-verificam-codurile') }}">Cum verificăm codurile</a></p>
    </section>

    {{-- Text propriu al magazinului, imediat sub oferte (câmpul „Text sub oferte” din fișa magazinului). --}}
    @if (trim((string) $store->offers_text) !== '')
        <section class="sec prose store__text">{!! $store->offers_text !!}</section>
    @endif

    @if ($deals->isNotEmpty())
        <section class="sec" aria-labelledby="s-deals">
            <div class="sec__head"><h2 id="s-deals">Deal-uri {{ $store->name }}</h2></div>
            <div class="deals">@foreach ($deals as $d) @include('partials.deal', ['d' => $d]) @endforeach</div>
        </section>
    @endif

    {{-- Întrebările proprii ale magazinului: sub oferte, deasupra descrierii. Fără întrebări completate, secțiunea nu apare. --}}
    @include('partials.faq', ['faq' => $faq, 'title' => $faqTitle])

    @if (trim(strip_tags((string) $store->description)) !== '')
        <section class="sec box prose" aria-labelledby="s-about">
            <h2 id="s-about">Despre {{ $store->name }}</h2>
            {!! $store->description !!}
        </section>
    @endif

    @include('partials.guide', ['b' => $guides['howto'], 'id' => 's-howto'])
    @include('partials.guide', ['b' => $guides['tips'], 'id' => 's-tips'])

    @if ($rows = $store->infoRows())
        <section class="sec" aria-labelledby="s-info">
            <h2 id="s-info">Informații utile</h2>
            <dl class="info">@foreach ($rows as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
        </section>
    @endif

    @if ($coupons->isNotEmpty() && $similar->isNotEmpty())
        <section class="sec" aria-labelledby="s-sim">
            <div class="sec__head"><h2 id="s-sim">Magazine similare</h2></div>
            <ul class="brands">@foreach ($similar as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
        </section>
    @endif
</div>
@endsection
