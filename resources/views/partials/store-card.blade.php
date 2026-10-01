{{-- Card mare de magazin (homepage → „Magazine populare”). --}}
@php($n = \App\Support\Catalog::count($s))
@php($best = \App\Support\Catalog::bestDeal($s))
<a class="shop" href="{{ $s->url() }}">
    <span class="shop__logo">
        @if ($s->logoUrl())
            <img src="{{ $s->logoUrl() }}" alt="" width="200" height="80" loading="eager" decoding="async">
        @else
            <span class="brand__ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
        @endif
    </span>
    <span class="shop__body">
        <span class="shop__name">{{ $s->name }}</span>
        <span class="shop__count">{{ \App\Support\Seo::plural($n, 'ofertă activă', 'oferte active') }}</span>
        @if ($best !== '')<span class="shop__deal">{{ $best }}</span>@endif
    </span>
    <span class="shop__cta">Vezi codurile <svg width="16" height="16"><use href="#i-arrow"/></svg></span>
</a>
