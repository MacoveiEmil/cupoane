@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Magazine A–Z</h1><p>Toate magazinele cu vouchere, reduceri și cupoane active acum.</p></header>
    @if ($groups->isEmpty())
        <p class="empty">Momentan nu sunt magazine cu oferte active.</p>
    @else
        <nav class="az" aria-label="Litere">@foreach ($groups->keys() as $l)<a href="#l-{{ \Illuminate\Support\Str::slug($l) }}">{{ $l }}</a>@endforeach</nav>
        @foreach ($groups as $l => $stores)
            <section class="az__group box" id="l-{{ \Illuminate\Support\Str::slug($l) }}">
                <h2>{{ $l }}</h2>
                <ul class="az__list">@foreach ($stores as $s)<li><a href="{{ $s->url() }}">{{ $s->name }}</a><span>{{ \App\Support\Catalog::count($s) }}</span></li>@endforeach</ul>
            </section>
        @endforeach
    @endif
</div>
@endsection
