{{-- Căsuță mică de ofertă (homepage: „Cele mai noi vouchere”, „Top coduri și oferte”). Același JS ca la card:
     primul click dezvăluie + copiază + deschide magazinul, următoarele doar copiază; codul apare în buton. --}}
@php
    $exp = $c->expiryLabel();
    $kind = $c->value_type === 'transport' ? 'ship' : (($c->value !== null && (float) $c->value > 0) ? 'val' : 'plain');
    $uses = (int) ($votes[$c->id]['uses'] ?? 0);
@endphp
<article class="tile" id="c-{{ $c->id }}">
    @if ($c->store)
        <a class="tile__store" href="{{ $c->store->url() }}">@if ($c->store->logoUrl())<img src="{{ $c->store->logoUrl() }}" alt="" width="56" height="18" loading="lazy" decoding="async">@endif<span>{{ $c->store->name }}</span></a>
    @endif
    <div class="tile__val tile__val--{{ $kind }}">@if ($c->upto && $kind === 'val')<small>până la</small>@endif<b>{{ $c->badge() }}</b></div>
    <h3 class="tile__title">{{ $c->title }}</h3>
    <p class="tile__meta">
        @if ($c->exclusive)<span class="tag tag--excl">Exclusiv</span>@endif
        @if ($exp && $exp['soon'])<span class="tag tag--soon">{{ $exp['text'] }}</span>@elseif ($uses >= 3)<span>Folosit de {{ \App\Support\Seo::plural($uses, 'dată', 'ori') }}</span>@elseif ($exp)<span class="tile__exp">{{ $exp['text'] }}</span>@endif
    </p>
    @if ($c->code)
        <a class="btn btn--code" href="{{ $c->goUrl() }}" target="_blank" rel="sponsored nofollow noopener" data-code="{{ $c->code }}">
            <span class="btn__code" aria-hidden="true">{{ $c->masked() }}</span>
            <span class="btn__label"><svg width="14" height="14"><use href="#i-copy"/></svg>Arată codul</span>
        </a>
        <noscript><p class="card__nojs">Cod: <code>{{ $c->code }}</code></p></noscript>
    @else
        <a class="btn btn--go" href="{{ $c->goUrl() }}" target="_blank" rel="sponsored nofollow noopener">Vezi oferta <svg width="14" height="14"><use href="#i-out"/></svg></a>
    @endif
</article>
