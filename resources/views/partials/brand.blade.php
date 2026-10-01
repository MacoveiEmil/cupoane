{{-- Magazin compact: logo mic, nume, cea mai bună reducere. --}}
@php($best = \App\Support\Catalog::bestDeal($s))
<li>
    <a class="brand" href="{{ $s->url() }}">
        <span class="brand__logo">
            @if ($s->logoUrl())
                <img src="{{ $s->logoUrl() }}" alt="" width="52" height="36" loading="lazy" decoding="async">
            @else
                <span class="brand__ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
            @endif
        </span>
        <span class="brand__txt">
            <span class="brand__name">{{ $s->name }}</span>
            <span class="brand__deal">{{ $best !== '' ? $best : \App\Support\Seo::plural(\App\Support\Catalog::count($s), 'ofertă', 'oferte') }}</span>
        </span>
    </a>
</li>
