@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head">
        <h1>Vouchere, reduceri și cupoane {{ $category->name }}</h1>
        @if ($coupons->currentPage() > 1)<p>Pagina {{ $coupons->currentPage() }}</p>@endif
    </header>
    @if ($coupons->currentPage() === 1 && $stores->isNotEmpty())
        <ul class="pills">@foreach ($stores as $s)<li><a href="{{ $s->url() }}">{{ $s->name }} <b>{{ \App\Support\Catalog::count($s) }}</b></a></li>@endforeach</ul>
    @endif
    @if ($coupons->isNotEmpty())
        <div class="cards">@foreach ($coupons as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
        @include('partials.pager', ['p' => $coupons])
    @else
        <p class="empty">Momentan nu sunt oferte active în această categorie.</p>
    @endif
    @if ($coupons->currentPage() === 1 && $category->description)
        <section class="sec box prose"><p>{{ $category->description }}</p></section>
    @endif
</div>
@endsection
