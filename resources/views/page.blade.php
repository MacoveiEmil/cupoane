@extends('layouts.app')

@php
    // Id-uri pe H2 + cuprins (de la 3 secțiuni).
    $toc = [];
    $body = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', function ($m) use (&$toc) {
        $id = \Illuminate\Support\Str::slug(strip_tags($m[2])) ?: 'sectiune-'.count($toc);
        $toc[] = [$id, strip_tags($m[2])];
        return '<h2'.$m[1].' id="'.e($id).'">'.$m[2].'</h2>';
    }, (string) $page->body);
@endphp

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <article class="article box">
        <h1>{{ $page->title }}</h1>
        <p class="article__meta">Ultima actualizare: <time datetime="{{ $page->updated_at->toDateString() }}">{{ \App\Support\Day::human($page->updated_at) }}</time></p>
        @if (count($toc) >= 3)
            <nav class="toc" aria-label="Cuprins"><details open><summary>Cuprins</summary><ol>@foreach ($toc as [$id, $t])<li><a href="#{{ $id }}">{{ $t }}</a></li>@endforeach</ol></details></nav>
        @endif
        <div class="prose">
            @if (trim(strip_tags($body)) === '')<p><em>Conținut în curs de redactare.</em></p>@else{!! $body !!}@endif
        </div>
    </article>
</div>
@endsection
