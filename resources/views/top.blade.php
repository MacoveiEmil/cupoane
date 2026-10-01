@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Top oferte</h1><p>Cele mai bune vouchere, reduceri și cupoane active acum.</p></header>

    @if ($featured->isNotEmpty())
        <section class="sec" aria-labelledby="s-best">
            <h2 id="s-best" class="sr">Cele mai bune oferte</h2>
            <div class="features">@foreach ($featured as $c) @include('partials.coupon', ['c' => $c, 'feature' => true]) @endforeach</div>
        </section>
    @endif

    @foreach ([['top', 'Cele mai accesate și recomandate', $top], ['exclusive', 'Coduri exclusive', $exclusive], ['recente', 'Adăugate recent', $recent]] as [$id, $title, $list])
        @if ($list->isNotEmpty())
            <section class="sec" aria-labelledby="{{ $id }}">
                <div class="sec__head"><h2 id="{{ $id }}">{{ $title }}</h2></div>
                <div class="cards">@foreach ($list as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
            </section>
        @endif
    @endforeach
    @if ($featured->isEmpty())
        <p class="empty">Momentan nu sunt oferte active.</p>
    @endif
</div>
@endsection
