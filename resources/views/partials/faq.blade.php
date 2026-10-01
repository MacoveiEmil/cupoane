{{-- Întrebări frecvente: titlu H2, întrebările ca <details> (aceleași și în schema FAQPage). --}}
@if ($faq)
    <section class="sec" aria-labelledby="s-faq">
        <h2 id="s-faq">{{ $title }}</h2>
        <div class="faq box">@foreach ($faq as [$q, $a])<details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>@endforeach</div>
    </section>
@endif
