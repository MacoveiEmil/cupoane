@extends('layouts.app')

@section('content')
<div class="wrap">
    @include('partials.crumbs')
    <header class="head"><h1>Deal-uri</h1><p>Produse anume la preț redus, verificate.</p></header>
    @if ($deals->isNotEmpty())
        <div class="deals deals--page">@foreach ($deals as $d) @include('partials.deal', ['d' => $d]) @endforeach</div>
        @include('partials.pager', ['p' => $deals])
    @else
        <p class="empty">Momentan nu avem deal-uri active. Revino curând!</p>
    @endif
</div>
@endsection
