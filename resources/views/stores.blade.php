@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Magazine</h1><p>Toate magazinele cu vouchere, reduceri și cupoane active acum.</p></header>
    @if ($groups->isEmpty())
        <p class="empty">Momentan nu sunt magazine cu oferte active.</p>
    @elseif ($groups->flatten(1)->count() < 16)
        {{-- Puține magazine: o singură grilă, fără împărțire pe litere. --}}
        <ul class="brands brands--tiles">@foreach ($groups->flatten(1) as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
    @else
        @if ($groups->count() > 1)
            <nav class="az" aria-label="Litere">@foreach ($groups->keys() as $l)<a href="#l-{{ \Illuminate\Support\Str::slug($l) }}">{{ $l }}</a>@endforeach</nav>
        @endif
        @foreach ($groups as $l => $stores)
            @php($slug = \Illuminate\Support\Str::slug($l))
            <section class="az__group" id="l-{{ $slug }}" aria-labelledby="h-{{ $slug }}">
                <h2 class="az__letter" id="h-{{ $slug }}">{{ $l }}</h2>
                <ul class="brands brands--tiles">@foreach ($stores as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul>
            </section>
        @endforeach
    @endif
</div>
@endsection
