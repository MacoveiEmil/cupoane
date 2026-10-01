{{-- Bloc de text editabil din admin (Setări și texte → Texte pagini): H2 + pași H3. --}}
@if ($b)
    <section class="sec guide" aria-labelledby="{{ $id }}">
        <h2 id="{{ $id }}">{{ $b['title'] }}</h2>
        <div class="guide__list">
            @foreach ($b['items'] as [$h, $p])
                <div class="guide__item">@if ($h)<h3>{{ $h }}</h3>@endif<p>{{ $p }}</p></div>
            @endforeach
        </div>
    </section>
@endif
