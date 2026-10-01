<?php

namespace App\Models;

use App\Support\Day;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Store extends Model
{
    public const NETWORKS = ['2performant' => '2Performant', 'profitshare' => 'Profitshare', 'direct' => 'Direct'];

    public const INFO_FIELDS = [
        'transport' => 'Transport',
        'prag_transport' => 'Transport gratuit de la',
        'retur' => 'Retur',
        'plata' => 'Metode de plată',
        'fidelitate' => 'Program de fidelitate',
        'prima_comanda' => 'Reducere la prima comandă',
        'studenti' => 'Reducere pentru studenți',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['info' => 'array', 'faq' => 'array', 'updated_on' => 'date', 'force_index' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Store $s) {
            if (! $s->slug) {
                $s->slug = Str::slug(str_replace('.', '-', $s->name)); // „Answear.ro” → „answear-ro”
            }
            // Slug-urile numerice ar intra în conflict cu /go/{id}.
            if (ctype_digit($s->slug)) {
                $s->slug = 'magazin-'.$s->slug;
            }
            $s->updated_on ??= Day::today();
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Pagina magazinului se indexează de la o ofertă activă, dacă are text „Despre” sau dacă e forțată din admin.
     * (Fără prag de 3: „cod reducere X” se caută și când magazinul are puține oferte.)
     */
    public function indexable(int $offers): bool
    {
        return $this->force_index || $offers > 0 || trim(strip_tags((string) $this->description)) !== '';
    }

    public function activeCoupons(): HasMany
    {
        return $this->coupons()->active();
    }

    public function targetUrl(): string
    {
        return (string) ($this->affiliate_url ?: $this->url);
    }

    public function goUrl(): string
    {
        return url('/go/'.$this->slug);
    }

    public function url(): string
    {
        return route('store', $this);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function touchUpdated(): void
    {
        if (! $this->updated_on || ! $this->updated_on->isSameDay(Day::today())) {
            $this->updated_on = Day::today();
            $this->saveQuietly();
        }
    }

    /** Informațiile utile completate, ca [eticheta, valoare]. */
    public function infoRows(): array
    {
        $rows = [];
        foreach (self::INFO_FIELDS as $key => $label) {
            if (! empty($this->info[$key])) {
                $rows[] = [$label, (string) $this->info[$key]];
            }
        }

        return $rows;
    }
}
