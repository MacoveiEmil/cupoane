<?php

namespace App\Models;

use App\Support\PageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Etichete tematice: transport-gratuit, prima-comanda, studenti, cumulabil (/oferte/{slug}). */
class Tag extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => PageCache::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class);
    }
}
