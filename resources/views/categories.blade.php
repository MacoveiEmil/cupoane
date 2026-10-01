@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Categorii de coduri de reducere</h1><p>Vouchere, reduceri și cupoane grupate pe tipuri de magazine.</p></header>
    @if ($tree->isEmpty())
        <p class="empty">Momentan nu sunt categorii cu oferte active.</p>
    @else
        <div class="sec__head"><h2>Toate categoriile</h2></div>
        <ul class="catcards">
            @foreach ($tree as $row)
                @php($logos = \App\Support\Catalog::storesWithOffers()->whereIn('id', $row['ids'])->take(4))
                <li class="catcard">
                    <a class="catcard__link" href="{{ route('category', $row['category']) }}">
                        <span class="catcard__icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['category']->name, 0, 1)) }}</span>
                        <span class="catcard__name">{{ $row['category']->name }}</span>
                        <span class="catcard__meta"><b>{{ \App\Support\Seo::plural($row['offers'], 'ofertă', 'oferte') }}</b> · {{ \App\Support\Seo::plural($row['stores'], 'magazin', 'magazine') }}</span>
                    </a>
                    @if ($logos->isNotEmpty())
                        <span class="catcard__logos" aria-hidden="true">
                            @foreach ($logos as $s)<span>@if ($s->logoUrl())<img src="{{ $s->logoUrl() }}" alt="" width="64" height="28" loading="lazy" decoding="async">@else{{ $s->name }}@endif</span>@endforeach
                        </span>
                    @endif
                    @if ($row['children']->where('offers', '>', 0)->isNotEmpty())
                        <span class="catcard__sub">@foreach ($row['children']->where('offers', '>', 0) as $ch)<a href="{{ route('category', $ch['category']) }}">{{ $ch['category']->name }}</a>@endforeach</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
