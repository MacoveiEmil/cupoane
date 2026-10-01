<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Nume')->required(),
                TextInput::make('slug')->label('Adresă (slug)')->prefix('/oferte/')->required()->unique(ignoreRecord: true),
                Textarea::make('description')->label('Text introductiv')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
