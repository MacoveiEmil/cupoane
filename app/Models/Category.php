<?php

namespace App\Models;

use App\Support\PageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Categorii pe maxim 2 niveluri, atribuite magazinelor. */
class Category extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            $c->slug = $c->slug ?: Str::slug($c->name);
            // Maxim 2 niveluri: un părinte nu poate avea el însuși părinte.
            if ($c->parent_id && Category::whereKey($c->parent_id)->whereNotNull('parent_id')->exists()) {
                $c->parent_id = null;
            }
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    /** ID-urile magazinelor din categorie și din subcategoriile ei. */
    public function storeIds(): array
    {
        $ids = $this->children()->pluck('id')->push($this->id);

        return \DB::table('category_store')->whereIn('category_id', $ids)->distinct()->pluck('store_id')->all();
    }
}
