<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('Pagină')->searchable()->weight('bold'),
                TextColumn::make('slug')->label('Adresă')->prefix('/')->color('gray'),
                ToggleColumn::make('in_footer')->label('În footer'),
                TextColumn::make('updated_at')->label('Ultima actualizare')->date('d.m.Y'),
            ])
            ->recordActions([
                Action::make('view')->label('Vezi')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (Page $r) => url('/'.$r->slug))->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
