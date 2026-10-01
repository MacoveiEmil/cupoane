<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Texte editabile ale site-ului, regulile de prospețime și datele de acces la rețele. */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Setări și texte';

    protected static ?string $title = 'Setări și texte';

    protected static ?string $slug = 'setari';

    public ?array $data = [];

    public function mount(): void
    {
        $values = Settings::all();
        foreach (Settings::SECRET as $key) {
            $values[$key] = ''; // secretele nu se reafișează; gol = neschimbat
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $saved = Settings::all();

        return $schema->statePath('data')->components([
            Tabs::make()->tabs([
                Tab::make('Texte site')->icon(Heroicon::OutlinedPencilSquare)->schema([
                    TextInput::make('hero_title')->label('Titlul mare de pe homepage (H1)')->required()->maxLength(90)
                        ->helperText('Păstrează formula „vouchere, reduceri și cupoane” pentru SEO.'),
                    Textarea::make('hero_subtitle')->label('Subtitlu homepage')->rows(2)->maxLength(220),
                    RichEditor::make('home_seo_text')->label('Text SEO de la finalul homepage-ului')
                        ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'undo', 'redo']),
                    Textarea::make('footer_about')->label('Text „Despre” din footer')->rows(2)->maxLength(300),
                    Textarea::make('affiliate_note')->label('Mențiunea de afiliere (footer)')->rows(2)->maxLength(300),
                ]),
                Tab::make('Aspect')->icon(Heroicon::OutlinedSwatch)->schema([
                    \Filament\Forms\Components\Radio::make('theme')->label('Tema site-ului')->required()
                        ->options(\App\Support\Assets::THEMES)
                        ->helperText('Poți previzualiza orice temă fără să o activezi: adaugă ?tema=a, ?tema=b sau ?tema=c la adresa unei pagini.'),
                ]),
                Tab::make('Reguli')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                    TextInput::make('recheck_days')->label('Reverificare oferte fără expirare (zile)')->numeric()->minValue(7)->maxValue(365)->required()
                        ->helperText('După acest interval fără verificare, oferta trece în ciornă, la „De reverificat”.'),
                    TextInput::make('vote_min')->label('Rata „A funcționat” apare de la (voturi)')->numeric()->minValue(1)->maxValue(100)->required(),
                    Toggle::make('trust_cf_ip')->label('Site-ul e în spatele Cloudflare (IP real din CF-Connecting-IP pentru voturi)'),
                    Toggle::make('auto_stores')->label('La import, creează automat magazine pentru programele acceptate'),
                ]),
                Tab::make('2Performant')->icon(Heroicon::OutlinedLink)->schema([
                    Toggle::make('tp_enabled')->label('Importă din 2Performant'),
                    Section::make('Date de acces')->description('Se păstrează criptat în baza de date. Câmpurile secrete nu se reafișează: lasă-le goale ca să rămână neschimbate.')->schema([
                        TextInput::make('tp_email')->label('Email cont afiliat')->email(),
                        TextInput::make('tp_password')->label('Parolă')->password()->revealable()
                            ->placeholder($saved['tp_password'] !== '' ? '•••••••• (salvată)' : ''),
                        TextInput::make('tp_user_key')->label('User key')->password()->revealable()
                            ->placeholder($saved['tp_user_key'] !== '' ? '•••••••• (salvat)' : '')
                            ->helperText('network.2performant.com → Settings, în contul de afiliat.'),
                    ]),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Salvează')->submit('save')->keyBindings(['mod+s'])])]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach (Settings::SECRET as $key) {
            if (($data[$key] ?? '') === '') {
                unset($data[$key]); // gol = păstrează valoarea salvată
            }
        }
        unset($data['ps_api_key']);
        Settings::set($data);
        \Illuminate\Support\Facades\Cache::forget('tp_auth');
        Notification::make()->title('Setările au fost salvate')->success()->send();
        $this->mount();
    }
}
