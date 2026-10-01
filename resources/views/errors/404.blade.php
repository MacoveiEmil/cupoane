@php($seo = new \App\Support\Seo(title: 'Pagina nu există | '.config('app.name'), noindex: true))
@extends('layouts.app')

@section('content')
<div class="wrap">
    <header class="head head--center">
        <p class="big404" aria-hidden="true">404</p>
        <h1>Ups, pagina asta nu există</h1>
        <p>Poate oferta a expirat sau adresa s-a schimbat. Caută magazinul:</p>
    </header>
    <div class="search-page search-page--center">@include('partials.search', ['big' => true])</div>
</div>
@endsection
