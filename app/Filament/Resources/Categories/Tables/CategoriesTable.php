<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('stores')->with('parent'))
            ->columns([
                TextColumn::make('name')->label('Categorie')->searchable()->sortable()->weight('bold'),
                TextColumn::make('parent.name')->label('Părinte')->placeholder('—'),
                TextColumn::make('stores_count')->label('Magazine')->badge()->sortable(),
                TextColumn::make('slug')->label('Adresă')->prefix('/categorie/')->color('gray'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
