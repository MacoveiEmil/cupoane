<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Conținut')->columnSpan(2)->schema([
                TextInput::make('title')->label('Titlu')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, $get) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
                RichEditor::make('body')->label('Text')
                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                    ->helperText('Titlurile H2 formează automat cuprinsul paginii (de la 3 secțiuni).'),
            ]),
            Section::make('Setări')->columnSpan(1)->schema([
                TextInput::make('slug')->label('Adresă (slug)')->prefix('/')->required()->unique(ignoreRecord: true),
                Toggle::make('in_footer')->label('Link în footer')->default(true),
                TextInput::make('sort')->label('Ordine în footer')->numeric()->default(0),
                TextInput::make('meta_title')->label('Titlu SEO (opțional)')->maxLength(70),
                Textarea::make('meta_description')->label('Descriere SEO (opțional)')->rows(3)->maxLength(160),
            ]),
        ]);
    }
}
