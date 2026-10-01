<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Resources\Deals\DealResource;
use App\Import\ImportException;
use App\Import\Importer;
use App\Models\Deal;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Lipești URL-ul produsului, alegi magazinul → ciornă cu deeplink generat după rețeaua magazinului.
            Action::make('quick')->label('Adaugă deal rapid')->icon(Heroicon::OutlinedBolt)->color('primary')
                ->schema([
                    TextInput::make('url')->label('URL produs')->url()->required(),
                    Select::make('store_id')->label('Magazin')->options(Store::orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                ])
                ->action(function (array $data) {
                    $store = Store::findOrFail($data['store_id']);
                    $deeplink = null;
                    $note = 'Magazinul nu are rețea/program: redirectul duce direct la produs, fără comision.';
                    $connector = Importer::connectors()[$store->network] ?? null;
                    if ($connector && $store->program_id) {
                        try {
                            $deeplink = $connector->deeplink($data['url'], $store->program_id);
                            $note = 'Deeplink '.$connector->label().' generat.';
                        } catch (ImportException $e) {
                            $note = $e->getMessage();
                        }
                    }
                    $path = trim((string) parse_url($data['url'], PHP_URL_PATH), '/');
                    $title = Str::ucfirst(trim(str_replace(['-', '_'], ' ', preg_replace('/\.(html?|php)$/i', '', basename($path)) ?: 'Deal nou')));
                    $deal = Deal::create(['store_id' => $store->id, 'title' => Str::limit($title, 120, ''), 'product_url' => $data['url'], 'deeplink' => $deeplink, 'status' => 'draft']);
                    Notification::make()->title('Deal creat ca ciornă')->body($note.' Completează prețurile și imaginea.')->success()->send();
                    $this->redirect(DealResource::getUrl('edit', ['record' => $deal]));
                }),
            CreateAction::make()->label('Adaugă manual'),
        ];
    }
}
