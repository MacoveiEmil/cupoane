<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Nume')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Adresă (slug)')->prefix('/categorie/')->required()->unique(ignoreRecord: true),
                Select::make('parent_id')->label('Categorie părinte')->placeholder('— niciuna (nivel 1) —')
                    ->relationship('parent', 'name', fn ($query, $record) => $query->whereNull('parent_id')->when($record, fn ($w) => $w->whereKeyNot($record->id))),
                TextInput::make('sort')->label('Ordine')->numeric()->default(0),
                Textarea::make('description')->label('Text sub listă (SEO)')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
