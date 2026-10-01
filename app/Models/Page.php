<?php

namespace App\Models;

use App\Support\PageCache;
use Illuminate\Database\Eloquent\Model;

/** Pagini statice (Despre noi, Contact, Termeni…), editabile din admin. */
class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['in_footer' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
