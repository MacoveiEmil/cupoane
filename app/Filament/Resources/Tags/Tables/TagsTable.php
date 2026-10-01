<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('coupons'))
            ->columns([
                TextColumn::make('name')->label('Etichetă')->weight('bold'),
                TextColumn::make('slug')->label('Adresă')->prefix('/oferte/')->color('gray'),
                TextColumn::make('coupons_count')->label('Cupoane')->badge(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
