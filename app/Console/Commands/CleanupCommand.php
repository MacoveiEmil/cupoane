<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Deal;
use App\Support\Day;
use App\Support\PageCache;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Curățarea zilnică (00:05, ora României):
 *  1. ofertele expirate → coș (soft delete); coșul se golește definitiv după 30 de zile;
 *  2. ofertele fără expirare, neverificate de N zile → ciornă + „De reverificat”;
 *  3. golirea cache-ului de pagini (etichetele „Expiră azi/mâine” și data zilei se schimbă).
 */
class CleanupCommand extends Command
{
    protected $signature = 'cupons:cleanup {--dry-run : Doar numără, fără modificări}';

    protected $description = 'Curățarea zilnică: expiratele în coș, ofertele neverificate în ciornă, cache golit';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $today = Day::today()->toDateString();
        $threshold = Day::today()->subDays(max(1, (int) Settings::get('recheck_days')))->toDateString();

        $expired = fn ($model) => $model::whereNotNull('expires_on')->whereDate('expires_on', '<', $today);
        $stale = fn ($model) => $model::where('status', 'published')->whereNull('expires_on')
            ->where(fn ($q) => $q->whereNull('verified_on')->orWhereDate('verified_on', '<', $threshold));
        $purgeable = fn ($model) => $model::onlyTrashed()->where('deleted_at', '<', now()->subDays(30));

        $counts = [
            'expired' => $expired(Coupon::class)->count() + $expired(Deal::class)->count(),
            'stale' => $stale(Coupon::class)->count() + $stale(Deal::class)->count(),
            'purged' => $purgeable(Coupon::class)->count() + $purgeable(Deal::class)->count(),
        ];

        if (! $dry) {
            foreach ([Coupon::class, Deal::class] as $model) {
                $expired($model)->each(fn ($o) => $o->delete());
                $stale($model)->update(['status' => 'draft', 'needs_recheck' => true]);
                $purgeable($model)->each(fn ($o) => $o->forceDelete());
            }
            DB::table('vote_guards')->where('created_at', '<', now()->subDays(2))->delete();
            PageCache::flush();
            cache()->forever('last_cleanup', ['at' => now()->toIso8601String()] + $counts);
        }

        $p = $dry ? '[simulare] ' : '';
        $this->line($p.'Expirate mutate în coș: '.$counts['expired']);
        $this->line($p.'Fără expirare, neverificate → ciornă: '.$counts['stale']);
        $this->line($p.'Șterse definitiv din coș (> 30 zile): '.$counts['purged']);
        $this->info($dry ? 'Simulare încheiată.' : 'Curățare încheiată.');

        return self::SUCCESS;
    }
}
