<?php

namespace App\Models;

use App\Support\Day;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Deal extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_old' => 'decimal:2',
            'price_new' => 'decimal:2',
            'expires_on' => 'date',
            'verified_on' => 'date',
            'needs_recheck' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Deal $d) {
            $old = (float) $d->price_old;
            $new = (float) $d->price_new;
            $d->percent = ($old > 0 && $new > 0 && $new < $old) ? (int) round(($old - $new) / $old * 100) : 0;
            $d->verified_on ??= Day::today();
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        $today = Day::today()->toDateString();

        return $q->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today));
    }

    public function isActive(): bool
    {
        return $this->status === 'published' && (! $this->expires_on || $this->expires_on->gte(Day::today()));
    }

    public function targetUrl(): string
    {
        return (string) ($this->deeplink ?: $this->product_url);
    }

    public function goUrl(): string
    {
        return url('/go/d'.$this->id);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }
}
