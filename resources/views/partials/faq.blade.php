{{-- Întrebări frecvente: H2 + fiecare întrebare H3 într-un <details>. Textul complet e în HTML-ul inițial
     (nu se încarcă la click), iar aceeași listă alimentează schema FAQPage. --}}
@if ($faq)
    <section class="sec" id="faq" aria-labelledby="s-faq">
        <h2 id="s-faq">{{ $title }}</h2>
        <div class="faq box">@foreach ($faq as [$q, $a])<details><summary><h3>{{ $q }}</h3></summary><p>{!! $a !!}</p></details>@endforeach</div>
    </section>
@endif
