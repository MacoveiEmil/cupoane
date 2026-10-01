@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')

    <header class="store">
        <span class="store__logo">
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="{{ $store->name }}" width="200" height="80" fetchpriority="high" decoding="async">
            @else
                <span class="shop__ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
            @endif
        </span>
        <div class="store__main">
            <h1>Cod reducere {{ $store->name }}</h1>
            <p class="store__meta">
                <span class="chip chip--mint"><b>{{ \App\Support\Seo::plural($coupons->count(), 'ofertă activă', 'oferte active') }}</b></span>
                <span class="chip">Actualizat la {{ \App\Support\Day::human($store->updated_on ?? \App\Support\Day::today()) }}</span>
            </p>
        </div>
        <a class="btn btn--go store__go" href="{{ $store->goUrl() }}" target="_blank" rel="sponsored nofollow noopener">Mergi la magazin <svg width="16" height="16"><use href="#i-out"/></svg></a>
    </header>

    <section class="sec" aria-labelledby="s-offers">
        <h2 id="s-offers" class="sr">Oferte {{ $store->name }}</h2>
        @if ($coupons->isNotEmpty())
            <div class="cards">@foreach ($coupons as $c) @include('partials.coupon', ['c' => $c, 'showStore' => false]) @endforeach</div>
        @else
            <div class="empty">
                <p><b>Momentan nu avem coduri active pentru {{ $store->name }}.</b></p>
                <p>Ofertele expirate sunt scoase automat, așa că aici apar doar coduri valabile. Revino curând sau încearcă un magazin similar.</p>
            </div>
        @endif
    </section>

    @if ($deals->isNotEmpty())
        <section class="sec" aria-labelledby="s-deals">
            <div class="sec__head"><h2 id="s-deals">Deal-uri {{ $store->name }}</h2></div>
            <div class="deals">@foreach ($deals as $d) @include('partials.deal', ['d' => $d]) @endforeach</div>
        </section>
    @endif

    @if ($rows = $store->infoRows())
        <section class="sec" aria-labelledby="s-info">
            <h2 id="s-info">Informații utile</h2>
            <dl class="info">@foreach ($rows as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
        </section>
    @endif

    @if (trim(strip_tags((string) $store->description)) !== '')
        <section class="sec box prose" aria-labelledby="s-about">
            <h2 id="s-about">Despre {{ $store->name }}</h2>
            {!! $store->description !!}
        </section>
    @endif

    @if ($faq)
        <section class="sec" aria-labelledby="s-faq">
            <h2 id="s-faq">Întrebări frecvente</h2>
            <div class="faq box">@foreach ($faq as [$q, $a])<details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>@endforeach</div>
        </section>
    @endif

    @if ($similar->isNotEmpty())
        <section class="sec" aria-labelledby="s-sim">
            <div class="sec__head"><h2 id="s-sim">Magazine similare</h2></div>
            <div class="shops">@foreach ($similar->take(3) as $i => $s) @include('partials.store-card', ['s' => $s, 'tone' => $i % 3 + 1]) @endforeach</div>
        </section>
    @endif
</div>
@endsection
