{{-- Card mare de magazin (homepage, magazine similare). $tone alternează culorile pastel. --}}
@php($n = \App\Support\Catalog::count($s))
<a class="shop shop--{{ $tone ?? 1 }}" href="{{ $s->url() }}">
    <span class="shop__logo">
        @if ($s->logoUrl())
            <img src="{{ $s->logoUrl() }}" alt="" width="160" height="64" loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}" decoding="async">
        @else
            <span class="shop__ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
        @endif
    </span>
    <span class="shop__name">{{ $s->name }}</span>
    <span class="shop__count">{{ \App\Support\Seo::plural($n, 'ofertă activă', 'oferte active') }}</span>
    @if (($best = \App\Support\Catalog::bestDeal($s)) !== '')<span class="shop__deal">{{ $best }}</span>@endif
    <span class="shop__cta">Vezi codurile <svg width="16" height="16"><use href="#i-arrow"/></svg></span>
</a>
