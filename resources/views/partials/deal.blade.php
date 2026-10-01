@php($fmt = fn ($v) => $v !== null ? number_format((float) $v, fmod((float) $v, 1) > 0 ? 2 : 0, ',', '.').' lei' : '')
<article class="deal" id="d-{{ $d->id }}">
    <a class="deal__img" href="{{ $d->goUrl() }}" target="_blank" rel="sponsored nofollow noopener" tabindex="-1" aria-hidden="true">
        @if ($d->imageUrl())<img src="{{ $d->imageUrl() }}" alt="" width="320" height="320" loading="lazy" decoding="async">@endif
        @if ($d->percent)<span class="deal__pct">−{{ $d->percent }}%</span>@endif
    </a>
    <div class="deal__body">
        @if ($d->store)<a class="card__store" href="{{ $d->store->url() }}">{{ $d->store->name }}</a>@endif
        <h3 class="deal__title">{{ $d->title }}</h3>
        <p class="deal__price">@if ($d->price_new)<b>{{ $fmt($d->price_new) }}</b>@endif @if ($d->price_old)<s>{{ $fmt($d->price_old) }}</s>@endif</p>
        <a class="btn btn--go btn--sm" href="{{ $d->goUrl() }}" target="_blank" rel="sponsored nofollow noopener">Vezi produsul <svg width="14" height="14"><use href="#i-out"/></svg></a>
    </div>
</article>
