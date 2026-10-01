@extends('layouts.app')

@section('content')
{{-- Sus doar titlul și căutarea: ofertele încep imediat dedesubt. --}}
<section class="intro">
    <div class="wrap">
        <div class="intro__in">
            <div>
                <h1 class="intro__title">{{ $site['hero_title'] }}</h1>
                <p class="intro__meta"><b>{{ \App\Support\Seo::plural($stats['offers'], 'ofertă activă', 'oferte active') }}</b> la <b>{{ \App\Support\Seo::plural($stats['stores'], 'magazin', 'magazine') }}</b> · actualizat azi, {{ \App\Support\Day::dayMonth() }}</p>
            </div>
            @include('partials.search', ['big' => true])
        </div>
    </div>
</section>

<div class="wrap">
    @if ($popular->isNotEmpty())
        <section class="sec" aria-labelledby="s-pop">
            <div class="sec__head"><h2 id="s-pop">Magazine populare</h2><a class="more" href="{{ route('stores') }}">Toate magazinele <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <div class="shops">@foreach ($popular as $s) @include('partials.store-card', ['s' => $s]) @endforeach</div>
        </section>
    @endif

    @php($lists = [['s-top', 'Top coduri și oferte', $top, route('top')], ['s-ex', 'Coduri exclusive', $exclusive, route('top').'#exclusive'], ['s-new', 'Adăugate recent', $recent, route('top').'#recente']])

    @foreach ($lists as $i => [$id, $title, $list, $more])
        @if ($list->isNotEmpty())
            <section class="sec" aria-labelledby="{{ $id }}">
                <div class="sec__head"><h2 id="{{ $id }}">{{ $title }}</h2><a class="more" href="{{ $more }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
                <div class="cards">@foreach ($list as $c) @include('partials.coupon', ['c' => $c]) @endforeach</div>
            </section>
        @endif

    @endforeach

    @if ($deals->isNotEmpty())
        <section class="sec" aria-labelledby="s-deals">
            <div class="sec__head"><h2 id="s-deals">Deal-uri</h2><a class="more" href="{{ route('deals') }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <div class="deals">@foreach ($deals as $d) @include('partials.deal', ['d' => $d]) @endforeach</div>
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section class="sec" aria-labelledby="s-cat">
            <div class="sec__head"><h2 id="s-cat">Categorii populare</h2><a class="more" href="{{ route('categories') }}">Vezi toate <svg width="16" height="16"><use href="#i-arrow"/></svg></a></div>
            <ul class="cats">
                @foreach ($categories as $row)
                    <li><a href="{{ route('category', $row['category']) }}">{{ $row['category']->name }}</a><span>{{ \App\Support\Seo::plural($row['stores'], 'magazin', 'magazine') }} · {{ \App\Support\Seo::plural($row['offers'], 'ofertă', 'oferte') }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (trim(strip_tags($site['home_seo_text'])) !== '')
        <section class="sec prose seo">{!! $site['home_seo_text'] !!}</section>
    @endif
</div>
@endsection
