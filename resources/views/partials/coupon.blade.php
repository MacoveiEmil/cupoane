{{-- Cardul de ofertă. Înălțime stabilă; codul se dezvăluie inline, fără layout shift. --}}
@php
    $exp = $c->expiryLabel();
    $v = $votes[$c->id] ?? null;
    $kind = $c->value_type === 'transport' ? 'ship' : (($c->value !== null && (float) $c->value > 0) ? 'val' : 'plain');
@endphp
<article class="card{{ ($feature ?? false) ? ' card--feature' : '' }}" id="c-{{ $c->id }}">
    @if (($feature ?? false) && $c->store)
        <a class="card__logo" href="{{ $c->store->url() }}" tabindex="-1" aria-hidden="true">
            @if ($c->store->logoUrl())<img src="{{ $c->store->logoUrl() }}" alt="" width="160" height="56" decoding="async">@else<span class="brand__ph">{{ mb_strtoupper(mb_substr($c->store->name, 0, 1)) }}</span>@endif
        </a>
    @endif
    <div class="sticker sticker--{{ $kind }}" aria-hidden="{{ $kind === 'plain' ? 'true' : 'false' }}">
        @if ($c->upto && $kind === 'val')<small>până la</small>@endif
        <b>{{ $c->badge() }}</b>
    </div>
    <div class="card__body">
        @if (($showStore ?? true) && $c->store)
            <a class="card__store" href="{{ $c->store->url() }}">
                @if ($c->store->logoUrl())<img src="{{ $c->store->logoUrl() }}" alt="" width="64" height="20" loading="lazy" decoding="async">@endif
                {{ $c->store->name }}
            </a>
        @endif
        @php($pre = $c->headingPrefix())
        <h3 class="card__title">@if ($pre !== '')<span class="card__pre">{{ $pre }}</span> @endif{{ $c->title }}</h3>
        <p class="card__meta">
            @if ($c->exclusive)<span class="tag tag--excl"><svg width="12" height="12"><use href="#i-star"/></svg>Exclusiv</span>@endif
            @if ($exp && $exp['soon'])<span class="tag tag--soon"><svg width="12" height="12"><use href="#i-clock"/></svg>{{ $exp['text'] }}</span>@endif
            @if ($c->verified_on)<span class="card__ok"><svg width="13" height="13"><use href="#i-check"/></svg>Verificat pe <time datetime="{{ $c->verified_on->toDateString() }}">{{ \App\Support\Day::human($c->verified_on) }}</time></span>@elseif ($c->created_at)<span>Adăugat pe <time datetime="{{ $c->created_at->toDateString() }}">{{ \App\Support\Day::human($c->created_at) }}</time></span>@endif
            @if ($exp && ! $exp['soon'])<span>{{ $exp['text'] }}</span>@endif
        </p>
        @if ($c->terms)
            <details class="card__terms"><summary>Condiții</summary><p>{{ $c->terms }}</p></details>
        @endif
    </div>
    <div class="card__act">
        @if ($c->code)
            <a class="btn btn--code" href="{{ $c->goUrl() }}" target="_blank" rel="sponsored nofollow noopener" data-code="{{ $c->code }}">
                <span class="btn__code" aria-hidden="true">{{ $c->masked() }}</span>
                <span class="btn__again" aria-hidden="true"><svg width="14" height="14"><use href="#i-copy"/></svg>Copiază din nou</span>
                <span class="btn__label"><svg width="16" height="16"><use href="#i-copy"/></svg>Arată codul</span>
            </a>
            <noscript><p class="card__nojs">Cod: <code>{{ $c->code }}</code></p></noscript>
            {{-- După dezvăluire: „Copiat!” + link spre magazin, în același rând (spațiu rezervat, fără layout shift). --}}
            <p class="card__status" aria-live="polite"></p>
        @else
            <a class="btn btn--go" href="{{ $c->goUrl() }}" target="_blank" rel="sponsored nofollow noopener">Vezi oferta <svg width="16" height="16"><use href="#i-out"/></svg></a>
        @endif
        {{-- „A funcționat?” doar la coduri (la ofertele fără cod nu are sens). --}}
        @if ($c->code)
        <div class="vote" data-vote="{{ $c->id }}">
            <span class="vote__q">A funcționat?</span>
            <button type="button" class="vote__btn" data-v="1" aria-label="Da, a funcționat"><svg width="14" height="14"><use href="#i-up"/></svg>Da</button>
            <button type="button" class="vote__btn" data-v="0" aria-label="Nu a funcționat"><svg width="14" height="14"><use href="#i-down"/></svg>Nu</button>
            <span class="vote__rate">@if ($v && $v['rate'] !== null){{ $v['rate'] }}% succes · {{ $v['total'] }} voturi @endif</span>
        </div>
        @endif
    </div>
</article>
