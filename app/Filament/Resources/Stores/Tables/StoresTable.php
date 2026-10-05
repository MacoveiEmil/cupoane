<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoresTable
{
    public const RELATIONS = ['accepted' => 'Acceptat', 'pending' => 'În așteptare', 'not_applied' => 'Neaplicat'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk('public')->imageHeight(28),
                TextColumn::make('name')->label('Magazin')->searchable()->sortable()->weight('bold'),
                TextColumn::make('active_count')->label('Oferte active')->sortable()->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('network')->label('Rețea')->badge()->formatStateUsing(fn ($state) => Store::NETWORKS[$state] ?? $state),
                TextColumn::make('program.relation')->label('Afiliere')->badge()
                    ->formatStateUsing(fn ($state) => self::RELATIONS[$state] ?? '—')
                    ->color(fn ($state) => match ($state) { 'accepted' => 'success', 'pending' => 'warning', default => 'gray' })
                    ->description(fn (Store $r) => $r->program?->relation !== 'accepted' && $r->program?->allows_coupons === false ? 'nu acceptă site-uri de cupoane' : null),
                TextInputColumn::make('home_order')->label('Ordine homepage')->type('number')->sortable()->width('8rem'),
                TextColumn::make('updated_on')->label('Actualizat')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('network')->label('Rețea')->options(Store::NETWORKS),
                SelectFilter::make('relation')->label('Afiliere')->options(self::RELATIONS)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null) ? $query->whereHas('program', fn ($p) => $p->where('relation', $data['value'])) : $query),
                TernaryFilter::make('allows_coupons')->label('Acceptă site-uri de cupoane')
                    ->queries(
                        true: fn ($query) => $query->whereHas('program', fn ($p) => $p->where('allows_coupons', true)),
                        false: fn ($query) => $query->whereHas('program', fn ($p) => $p->where('allows_coupons', false)),
                    ),
                TernaryFilter::make('has_offers')->label('Oferte active')
                    ->queries(
                        true: fn ($query) => $query->whereHas('coupons', fn ($c) => $c->active()),
                        false: fn ($query) => $query->whereDoesntHave('coupons', fn ($c) => $c->active()),
                    ),
            ])
            ->recordActions([
                Action::make('view')->label('Vezi')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (Store $r) => $r->url())->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
