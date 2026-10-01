<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Models\Deal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->imageHeight(36),
                TextColumn::make('store.name')->label('Magazin')->sortable(),
                TextColumn::make('title')->label('Produs')->searchable()->limit(60)->wrap(),
                TextColumn::make('price_new')->label('Preț')->formatStateUsing(fn ($state, Deal $r) => $state ? number_format((float) $state, 2, ',', '.').' lei'.($r->percent ? ' (−'.$r->percent.'%)' : '') : '—'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'published' ? 'Publicat' : 'Ciornă')
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('expires_on')->label('Expiră')->date('d.m.Y')->placeholder('fără dată')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
