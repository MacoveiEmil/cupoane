<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Models\Coupon;
use App\Support\Day;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Oferta')->columnSpan(2)->schema([
                Select::make('store_id')->label('Magazin')->relationship('store', 'name')->searchable()->preload()->required(),
                TextInput::make('title')->label('Titlu')->required()->maxLength(250)
                    ->placeholder('ex.: 15% reducere la toate produsele'),
                Grid::make(2)->schema([
                    TextInput::make('code')->label('Cod')->maxLength(64)
                        ->helperText('Lasă gol pentru o ofertă fără cod (butonul devine „Vezi oferta”).'),
                    TextInput::make('affiliate_url')->label('Link de afiliere (opțional)')->url()->maxLength(2048)
                        ->helperText('Gol = linkul implicit al magazinului.'),
                ]),
                Grid::make(3)->schema([
                    TextInput::make('value')->label('Valoare')->numeric()->minValue(0),
                    Select::make('value_type')->label('Tip')->options(Coupon::TYPES)->default('procent')->required(),
                    Toggle::make('upto')->label('„până la”')->inline(false)->helperText('Reducere maximă, nu fixă'),
                ]),
                Textarea::make('terms')->label('Condiții')->rows(3),
            ]),
            Section::make('Publicare')->columnSpan(1)->schema([
                ToggleButtons::make('status')->label('Status')->inline()->required()->default('draft')
                    ->options(['draft' => 'Ciornă', 'published' => 'Publicat'])
                    ->colors(['draft' => 'gray', 'published' => 'success']),
                DatePicker::make('starts_on')->label('Începe la')->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('expires_on')->label('Expiră la')->native(false)->displayFormat('d.m.Y')
                    ->helperText('Inclusiv. Fără dată = se cere reverificare periodică.'),
                DatePicker::make('verified_on')->label('Verificat la')->native(false)->displayFormat('d.m.Y')->default(fn () => Day::today()),
                Toggle::make('exclusive')->label('Exclusiv'),
                Toggle::make('top')->label('Top ofertă'),
                Select::make('tags')->label('Etichete')->relationship('tags', 'name')->multiple()->preload(),
                Placeholder::make('ext_id')->label('ID extern')->content(fn ($record) => $record?->ext_id ?: '—')
                    ->visible(fn ($record) => (bool) $record?->ext_id),
            ]),
        ]);
    }
}
