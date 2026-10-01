<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Store;
use App\Support\Images;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Toggle;
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
                FileUpload::make('logo_path')->label('Logo')->image()->acceptedFileTypes(Images::MIME)->disk('public')->directory('logos')->maxSize(2048)
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => Images::fromUpload($file, 'logos', 'logo_path', 320, 160))
                    ->helperText('PNG, JPG sau WebP (fără SVG); se micșorează și se salvează automat WebP.'),
                Toggle::make('force_index')->label('Indexează pagina și fără oferte')
                    ->helperText('Implicit, pagina e indexată în Google dacă are cel puțin o ofertă activă sau text „Despre magazin”.'),
                TextInput::make('home_order')->label('Ordine pe homepage')->numeric()->minValue(0)->maxValue(999)->default(0)
                    ->helperText('1, 2, 3 = cardurile mari de pe homepage. 0 = nu apare acolo.'),
                Grid::make(2)->schema([
                    Select::make('network')->label('Rețea')->options(Store::NETWORKS),
                    TextInput::make('program_id')->label('ID program'),
                ]),
            ]),
            Section::make('Conținut unic al paginii')->columnSpanFull()
                ->description('Texte scrise doar pentru acest magazin (de tine sau de un script / AI: vezi „php artisan cupons:content”). Fără texte generale repetate pe toate paginile; ce rămâne gol nu apare pe site.')
                ->schema([
                    RichEditor::make('offers_text')->label('Text sub oferte')
                        ->helperText('Apare imediat sub lista de coduri. 1–3 paragrafe despre reducerile obișnuite ale magazinului (cuvinte cheie: cod reducere, voucher, cupon + numele magazinului).')
                        ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'undo', 'redo']),
                    RichEditor::make('description')->label('Despre magazin')
                        ->toolbarButtons(['bold', 'italic', 'link', 'h3', 'bulletList', 'orderedList', 'undo', 'redo']),
                    Repeater::make('howto')->label('Cum folosești codurile (pași)')->schema([
                        TextInput::make('t')->label('Pas (H3)')->required()->maxLength(140),
                        Textarea::make('p')->label('Explicație')->rows(2)->required(),
                    ])->addActionLabel('Adaugă pas')->reorderable()->collapsible()->defaultItems(0)->maxItems(6),
                    Repeater::make('tips')->label('Cum economisești (sfaturi)')->schema([
                        TextInput::make('t')->label('Sfat (H3)')->required()->maxLength(140),
                        Textarea::make('p')->label('Explicație')->rows(2)->required(),
                    ])->addActionLabel('Adaugă sfat')->reorderable()->collapsible()->defaultItems(0)->maxItems(6),
                ]),
            Section::make('Informații utile')->description('Apar pe pagina magazinului doar cele completate.')->columnSpanFull()->columns(3)->collapsible()->schema($info),
            Section::make('Întrebări frecvente')->description('3–5 întrebări specifice magazinului (ex.: cod pentru prima comandă, cumulare cu alte promoții, prag pentru transport gratuit). Apar sub oferte și în schema FAQPage. Linkuri în răspuns: [text](/adresa). Fără întrebări, secțiunea nu apare.')->columnSpanFull()->collapsible()->schema([
                Repeater::make('faq')->label('')->schema([
                    TextInput::make('q')->label('Întrebare')->required(),
                    Textarea::make('a')->label('Răspuns')->rows(2)->required(),
                ])->addActionLabel('Adaugă întrebare')->reorderable()->collapsible()->defaultItems(0),
            ]),
        ]);
    }
}
