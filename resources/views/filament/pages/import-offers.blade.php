<x-filament-panels::page>
    <div @if ($this->isRunning()) wire:poll.1s="tick" @endif class="space-y-6">
        @foreach ($networks as $id => $n)
            <x-filament::section :heading="$n['label']" icon="heroicon-o-link">
                @if (! $n['enabled'])
                    <p class="text-sm text-gray-500">Rețeaua e dezactivată. O poți activa din <a class="underline" href="{{ \App\Filament\Pages\SiteSettings::getUrl() }}">Setări și texte</a>.</p>
                @elseif (! $n['configured'])
                    <p class="text-sm text-gray-500">Completează datele de acces în <a class="underline" href="{{ \App\Filament\Pages\SiteSettings::getUrl() }}">Setări și texte → 2Performant</a>.</p>
                @else
                    @php($s = $n['state'])
                    <div class="flex flex-wrap items-center gap-4">
                        <x-filament::button wire:click="start('{{ $id }}')" icon="heroicon-o-arrow-down-tray" :disabled="\App\Import\Importer::running($s) && $s['error'] === ''">
                            Importă acum
                        </x-filament::button>
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            @switch($s['phase'])
                                @case('idle') Nu a rulat încă. @break
                                @case('programs') În curs: programe acceptate… @break
                                @case('directory') În curs: magazine-placeholder (programe neaplicate)… @break
                                @case('offers') În curs: oferte… @break
                                @case('logos') În curs: logo-uri… @break
                                @default Terminat {{ $s['finished'] ? \Carbon\Carbon::parse($s['finished'])->timezone(config('app.timezone'))->format('d.m.Y H:i') : '' }}.
                            @endswitch
                        </span>
                    </div>
                    @if ($s['phase'] !== 'idle')
                        <p class="mt-3 text-sm">{{ $this->summary($s) }}</p>
                    @endif
                    @if ($s['error'])
                        <p class="mt-3 text-sm text-danger-600">{{ $s['error'] }} — apasă din nou „Importă acum” după ce rezolvi problema.</p>
                    @endif
                    <p class="mt-3 text-xs text-gray-500">Ofertele noi intră ca ciorne (Cupoane → Ciorne), inclusiv cele de la magazinele la care nu suntem încă acceptați (link direct spre magazin până la acceptare, apoi automat link de afiliere). Cele existente își actualizează doar codul, datele și linkul; cele șterse nu revin.</p>
                @endif
            </x-filament::section>
        @endforeach

        <x-filament::section heading="Programe neasociate" description="Programe acceptate care nu sunt legate de un magazin. Ofertele lor se importă după asociere." collapsible>
            @if ($unmapped->isEmpty())
                <p class="text-sm text-gray-500">Nimic de asociat.</p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($unmapped as $p)
                        <div class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-[14rem] flex-1">
                                <div class="font-medium">{{ $p->name }}</div>
                                <div class="text-xs text-gray-500">{{ $p->network }} · {{ $p->program_id }} · {{ $p->site_url }}</div>
                            </div>
                            <select wire:model="assoc.{{ $p->id }}" class="rounded-lg border-gray-300 text-sm dark:bg-white/5">
                                <option value="new">+ Magazin nou „{{ $p->name }}”</option>
                                @foreach ($stores as $sid => $sname)
                                    <option value="{{ $sid }}">{{ $sname }}</option>
                                @endforeach
                            </select>
                            <x-filament::button size="sm" color="gray" wire:click="associate({{ $p->id }})">Asociază</x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
