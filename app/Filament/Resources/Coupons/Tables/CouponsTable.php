<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use App\Support\Day;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('store.name')->label('Magazin')->searchable()->sortable(),
                TextColumn::make('title')->label('Ofertă')->searchable()->limit(60)->wrap()
                    ->description(fn (Coupon $r) => $r->code ? 'Cod: '.$r->code : 'Fără cod'),
                TextColumn::make('badge')->label('Valoare')->state(fn (Coupon $r) => ($r->upto ? 'până la ' : '').$r->badge())->badge()->color('primary'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->trashed() ? 'În coș' : ($r->needs_recheck ? 'De reverificat' : ($state === 'published' ? ($r->isActive() ? 'Activ' : 'Publicat (inactiv)') : 'Ciornă')))
                    ->color(fn ($state, Coupon $r) => $r->trashed() ? 'danger' : ($r->needs_recheck ? 'warning' : ($state === 'published' ? ($r->isActive() ? 'success' : 'gray') : 'gray'))),
                TextColumn::make('expires_on')->label('Expiră')->date('d.m.Y')->sortable()->placeholder('fără dată')
                    ->color(fn (Coupon $r) => $r->expires_on && $r->expires_on->lte(Day::today()->addDays(3)) ? 'warning' : null),
                TextColumn::make('verified_on')->label('Verificat')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(['draft' => 'Ciornă', 'published' => 'Publicat']),
                SelectFilter::make('store_id')->label('Magazin')->relationship('store', 'name')->searchable()->preload(),
                TrashedFilter::make()->label('Coș'),
            ])
            ->recordActions([
                Action::make('verify')->label('Verificat azi')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (Coupon $r) => ! $r->trashed() && ($r->status === 'draft' || $r->needs_recheck || ! $r->verified_on?->isSameDay(Day::today())))
                    ->action(fn (Coupon $r) => $r->update(['status' => 'published', 'needs_recheck' => false, 'verified_on' => Day::today()]))
                    ->successNotificationTitle('Oferta e publicată și marcată verificată azi'),
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->label('Verificat azi și publică')->icon(Heroicon::OutlinedCheckBadge)
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'published', 'needs_recheck' => false, 'verified_on' => Day::today()]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()->label('Mută în coș'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
