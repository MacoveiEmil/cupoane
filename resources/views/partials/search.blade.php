@php($sid = $big ? 'q-hero' : 'q-'.\Illuminate\Support\Str::random(4))
<form class="search{{ $big ? ' search--big' : '' }}" role="search" action="{{ route('search') }}" method="get" data-search data-index="{{ route('search.index') }}">
    <label class="sr" for="{{ $sid }}">Caută un magazin</label>
    <div class="search__row">
        <input class="search__input" id="{{ $sid }}" type="search" name="q" value="{{ $q ?? '' }}" placeholder="Caută un magazin…" autocomplete="off" spellcheck="false"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $sid }}-list">
        <button class="search__btn" type="submit"><svg width="20" height="20"><use href="#i-search"/></svg><span class="sr">Caută</span></button>
    </div>
    <ul class="search__list" id="{{ $sid }}-list" role="listbox" hidden></ul>
</form>
