@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Categorii</h1><p>Vouchere, reduceri și cupoane grupate pe tipuri de magazine.</p></header>
    @if ($tree->isEmpty())
        <p class="empty">Momentan nu sunt categorii cu oferte active.</p>
    @else
        <ul class="cats cats--big">
            @foreach ($tree as $row)
                <li>
                    <a href="{{ route('category', $row['category']) }}">{{ $row['category']->name }}</a>
                    <span>{{ \App\Support\Seo::plural($row['stores'], 'magazin', 'magazine') }} · {{ \App\Support\Seo::plural($row['offers'], 'ofertă', 'oferte') }}</span>
                    @if ($row['children']->where('offers', '>', 0)->isNotEmpty())
                        <span class="cats__sub">@foreach ($row['children']->where('offers', '>', 0) as $ch)<a href="{{ route('category', $ch['category']) }}">{{ $ch['category']->name }}</a>@endforeach</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
