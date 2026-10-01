<?php

namespace App\Filament\Widgets;

use App\Models\Coupon;
use App\Models\Store;
use App\Support\Day;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Cifrele de pe prima pagină a adminului. */
class SiteStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Pe scurt';

    protected function getStats(): array
    {
        $today = Day::today();
        $clicks = (int) DB::table('clicks')->whereDate('date', '>=', $today->subDays(6)->toDateString())->sum('count');

        return [
            Stat::make('Oferte active pe site', Coupon::active()->count())
                ->description(Store::whereHas('coupons', fn ($query) => $query->active())->count().' magazine cu oferte')
                ->color('success'),
            Stat::make('Ciorne de verificat', Coupon::where('status', 'draft')->where('needs_recheck', false)->count())
                ->description('Din import sau adăugate manual')->color('warning'),
            Stat::make('De reverificat', Coupon::where('needs_recheck', true)->count())
                ->description('Fără expirare, neverificate de mult')->color('danger'),
            Stat::make('Click-uri în ultimele 7 zile', $clicks)
                ->description('Ieșiri spre magazine prin /go/'),
            Stat::make('Expiră în 7 zile', Coupon::active()->whereNotNull('expires_on')->whereDate('expires_on', '<=', $today->addDays(7))->count())
                ->description('Merită căutate oferte noi'),
        ];
    }
}
