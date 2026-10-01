<?php

namespace App\Filament\Resources\Deals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Produs')->columnSpan(2)->schema([
                Select::make('store_id')->label('Magazin')->relationship('store', 'name')->searchable()->preload()->required(),
                TextInput::make('title')->label('Nume produs')->required()->maxLength(250),
                TextInput::make('product_url')->label('URL produs')->url()->required()->maxLength(2048),
                TextInput::make('deeplink')->label('Deeplink de afiliere')->url()->maxLength(2048)
                    ->helperText('Gol = redirect direct la produs (fără comision). „Adaugă deal rapid” îl generează automat.'),
                Grid::make(2)->schema([
                    TextInput::make('price_old')->label('Preț vechi (lei)')->numeric()->minValue(0),
                    TextInput::make('price_new')->label('Preț nou (lei)')->numeric()->minValue(0),
                ]),
            ]),
            Section::make('Publicare')->columnSpan(1)->schema([
                ToggleButtons::make('status')->label('Status')->inline()->required()->default('draft')
                    ->options(['draft' => 'Ciornă', 'published' => 'Publicat'])->colors(['draft' => 'gray', 'published' => 'success']),
                FileUpload::make('image_path')->label('Imagine')->image()->disk('public')->directory('deals')->maxSize(3072),
                DatePicker::make('expires_on')->label('Expiră la')->native(false)->displayFormat('d.m.Y'),
            ]),
        ]);
    }
}
