@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head">
        <h1>{{ $tag->name }}: vouchere, reduceri și cupoane</h1>
        @if ($tag->description && $coupons->currentPage() === 1)<p>{{ $tag->description }}</p>@endif
    </header>
    @if ($coupons->isNotEmpty())
        <div class="cards">@foreach ($coupons as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
        @include('partials.pager', ['p' => $coupons])
    @else
        <p class="empty">Momentan nu sunt oferte active cu această etichetă.</p>
    @endif
</div>
@endsection
