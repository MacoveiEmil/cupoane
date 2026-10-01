@if ($seo->breadcrumbs)
<nav class="crumbs" aria-label="Breadcrumb">
    <ol>
        <li><a href="{{ url('/') }}">Acasă</a></li>
        @foreach ($seo->breadcrumbs as $i => [$name, $url])
            @if ($loop->last)
                <li><span aria-current="page">{{ $name }}</span></li>
            @else
                <li><a href="{{ $url }}">{{ $name }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
@endif
