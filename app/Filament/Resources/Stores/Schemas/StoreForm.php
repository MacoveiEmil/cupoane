<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Store;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        $info = [];
        foreach (Store::INFO_FIELDS as $key => $label) {
            $info[] = TextInput::make('info.'.$key)->label($label)->maxLength(190);
        }

        return $schema->columns(3)->components([
            Section::make('Magazin')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(190)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Adresă (slug)')->prefix('/magazin/')->required()->unique(ignoreRecord: true)->maxLength(190),
                TextInput::make('url')->label('Site-ul magazinului')->url()->maxLength(255),
                TextInput::make('affiliate_url')->label('Link de afiliere implicit')->url()->maxLength(2048)
                    ->helperText('Folosit de „Mergi la magazin” și de ofertele fără link propriu.'),
                Select::make('categories')->label('Categorii')->relationship('categories', 'name')->multiple()->preload()->searchable()->columnSpanFull(),
            ]),
            Section::make('Logo și afișare')->columnSpan(1)->schema([
                FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('logos')->maxSize(2048)
                    ->helperText('PNG/JPG/WebP; se convertește automat în WebP.'),
                TextInput::make('home_order')->label('Ordine pe homepage')->numeric()->minValue(0)->maxValue(999)->default(0)
                    ->helperText('1, 2, 3 = cardurile mari de pe homepage. 0 = nu apare acolo.'),
                Grid::make(2)->schema([
                    Select::make('network')->label('Rețea')->options(Store::NETWORKS),
                    TextInput::make('program_id')->label('ID program'),
                ]),
            ]),
            Section::make('Texte pentru pagina magazinului')->columnSpanFull()->schema([
                RichEditor::make('description')->label('Despre magazin')
                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'undo', 'redo']),
            ]),
            Section::make('Informații utile')->description('Apar pe pagina magazinului doar cele completate.')->columnSpanFull()->columns(3)->collapsible()->schema($info),
            Section::make('Întrebări frecvente')->description('Apar pe pagină și ca rezultat îmbogățit în Google (FAQ).')->columnSpanFull()->collapsible()->schema([
                Repeater::make('faq')->label('')->schema([
                    TextInput::make('q')->label('Întrebare')->required(),
                    Textarea::make('a')->label('Răspuns')->rows(2)->required(),
                ])->addActionLabel('Adaugă întrebare')->reorderable()->collapsible()->defaultItems(0),
            ]),
        ]);
    }
}
