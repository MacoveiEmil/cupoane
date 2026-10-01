<?php

namespace App\Models;

use App\Support\Day;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use SoftDeletes;

    public const TYPES = ['procent' => '%', 'lei' => 'lei', 'transport' => 'Transport gratuit'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'upto' => 'boolean',
            'exclusive' => 'boolean',
            'top' => 'boolean',
            'needs_recheck' => 'boolean',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'verified_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $c) {
            $c->code = $c->code !== null ? (preg_replace('/\s+/u', '', trim($c->code)) ?: null) : null;
            // Adăugate manual = verificate azi. Cele importate rămân neverificate până la „Verificat azi” din admin.
            if (! $c->ext_id) {
                $c->verified_on ??= Day::today();
            }
        });
        static::saved(function (Coupon $c) {
            if ($c->status === 'published') {
                $c->store?->touchUpdated();
            }
            PageCache::flush();
        });
        static::deleted(fn () => PageCache::flush());
        static::restored(fn () => PageCache::flush());
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** Singura definiție a „ofertei active”: publicată, începută, neexpirată (expirarea e inclusivă). */
    public function scopeActive(Builder $q): Builder
    {
        $today = Day::today()->toDateString();

        return $q->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today));
    }

    /** Ordinea din listări: exclusive → verificate recent → cele mai noi. */
    public function scopeRanked(Builder $q): Builder
    {
        return $q->orderByDesc('exclusive')->orderByDesc('verified_on')->orderByDesc('id');
    }

    public function isActive(): bool
    {
        $today = Day::today();

        return $this->status === 'published'
            && (! $this->starts_on || $this->starts_on->lte($today))
            && (! $this->expires_on || $this->expires_on->gte($today));
    }

    public function targetUrl(): string
    {
        return $this->affiliate_url ?: (string) $this->store?->targetUrl();
    }

    public function goUrl(): string
    {
        return url('/go/'.$this->id);
    }

    public function masked(): string
    {
        return $this->code ? mb_substr($this->code, 0, 2).'****' : '';
    }

    /**
     * Începutul titlului H3, pentru căutări de tip „cod reducere {magazin}”: „Cod reducere Answear.ro:”.
     * Gol dacă titlul conține deja numele magazinului.
     */
    public function headingPrefix(): string
    {
        $name = (string) $this->store?->name;
        $base = preg_replace('/\.(ro|com|eu|net|org)$/i', '', $name);
        $title = Str::lower(Str::ascii((string) $this->title));
        if ($name === '' || str_contains($title, Str::lower(Str::ascii($base)))) {
            return '';
        }

        return match (true) {
            (bool) $this->code => "Cod reducere {$name}:",
            str_starts_with($title, 'reduc') => "Ofertă {$name}:",
            default => "Reducere {$name}:",
        };
    }

    /** Textul badge-ului: „−20%”, „50 lei”, „Transport gratuit”, „Cod” / „Ofertă”. */
    public function badge(): string
    {
        if ($this->value_type === 'transport') {
            return 'Transport gratuit';
        }
        if ($this->value !== null && (float) $this->value > 0) {
            $n = str_replace('.', ',', rtrim(rtrim((string) $this->value, '0'), '.'));

            return $this->value_type === 'lei' ? $n.' lei' : '−'.$n.'%';
        }

        return $this->code ? 'Cod' : 'Ofertă';
    }

    /** null | ['text' => …, 'soon' => bool] */
    public function expiryLabel(): ?array
    {
        if (! $this->expires_on) {
            return null;
        }
        $today = Day::today();
        if ($this->expires_on->isSameDay($today)) {
            return ['text' => 'Expiră azi', 'soon' => true];
        }
        if ($this->expires_on->isSameDay($today->copy()->addDay())) {
            return ['text' => 'Expiră mâine', 'soon' => true];
        }

        return ['text' => 'Valabil până la '.Day::human($this->expires_on), 'soon' => false];
    }
}
