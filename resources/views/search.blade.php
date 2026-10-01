@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>{{ $q !== '' ? 'Rezultate pentru „'.$q.'”' : 'Caută un magazin' }}</h1></header>
    <div class="search-page">@include('partials.search', ['big' => true, 'q' => $q])</div>
    @if ($q !== '')
        @if ($stores->isNotEmpty())
            <section class="sec" aria-label="Magazine"><ul class="brands">@foreach ($stores as $s) @include('partials.brand', ['s' => $s]) @endforeach</ul></section>
        @endif
        @if ($cats->isNotEmpty())
            <section class="sec" aria-labelledby="r-cat"><h2 id="r-cat">Categorii</h2><ul class="pills">@foreach ($cats as $c)<li><a href="{{ route('category', $c) }}">{{ $c->name }}</a></li>@endforeach</ul></section>
        @endif
        @if ($stores->isEmpty() && $cats->isEmpty())
            <p class="empty">N-am găsit nimic. Verifică scrierea sau caută în <a href="{{ route('stores') }}">lista de magazine</a>.</p>
        @endif
    @endif
</div>
@endsection
