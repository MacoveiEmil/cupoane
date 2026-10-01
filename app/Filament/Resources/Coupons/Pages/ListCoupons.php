<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Models\Coupon;
use App\Support\Day;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Adaugă cupon')];
    }

    /** Taburi rapide deasupra listei. */
    public function getTabs(): array
    {
        $soon = fn ($query) => $query->active()->whereNotNull('expires_on')->whereDate('expires_on', '<=', Day::today()->addDays(7));

        return [
            'all' => Tab::make('Toate'),
            'active' => Tab::make('Active pe site')->modifyQueryUsing(fn ($query) => $query->active())
                ->badge(Coupon::active()->count()),
            'draft' => Tab::make('Ciorne')->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')->where('needs_recheck', false))
                ->badge(Coupon::where('status', 'draft')->where('needs_recheck', false)->count() ?: null)->badgeColor('warning'),
            'recheck' => Tab::make('De reverificat')->modifyQueryUsing(fn ($query) => $query->where('needs_recheck', true))
                ->badge(Coupon::where('needs_recheck', true)->count() ?: null)->badgeColor('danger'),
            'soon' => Tab::make('Expiră în 7 zile')->modifyQueryUsing($soon),
        ];
    }
}
