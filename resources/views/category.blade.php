@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head">
        <h1>{{ $h1 }}</h1>
        <p>{{ \App\Support\Seo::plural($coupons->total(), 'ofertă activă', 'oferte active') }}@if ($coupons->currentPage() > 1) · pagina {{ $coupons->currentPage() }}@endif</p>
    </header>

    <section class="sec" aria-labelledby="s-offers">
        <div class="sec__head"><h2 id="s-offers">Cele mai bune oferte la {{ mb_strtolower($category->name) }}</h2></div>
        @if ($coupons->isNotEmpty())
            <div class="cards">@foreach ($coupons as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
            @include('partials.pager', ['p' => $coupons])
        @else
            <p class="empty">Momentan nu sunt oferte active în această categorie.</p>
        @endif
    </section>

    @if ($coupons->currentPage() === 1 && $stores->isNotEmpty())
        <section class="sec" aria-labelledby="s-stores">
            <div class="sec__head"><h2 id="s-stores">Magazine cu reduceri la {{ mb_strtolower($category->name) }}</h2></div>
            <ul class="brands">@foreach ($stores as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
        </section>
    @endif

    @if ($coupons->currentPage() === 1 && $category->description)
        <section class="sec prose seo"><p>{{ $category->description }}</p></section>
    @endif
</div>
@endsection
