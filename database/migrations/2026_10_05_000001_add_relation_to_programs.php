<?php

use App\Import\Categories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Starea relației cu programul (acceptat / în așteptare / neaplicat) + dacă acceptă site-uri de cupoane; categorii în română. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $t) {
            $t->string('relation', 20)->nullable()->index();     // accepted | pending | not_applied
            $t->boolean('allows_coupons')->nullable();             // programul acceptă site-uri de cupoane (sursa „Coupons/Vouchers”)
        });
        DB::table('programs')->where('accepted', true)->update(['relation' => 'accepted']);

        foreach (Categories::RO as $en => $ro) {
            [$name, $slug] = Categories::ro($en);
            DB::table('categories')->where('name', $en)->whereNotExists(fn ($q) => $q->from('categories as c2')->where('c2.slug', $slug))
                ->update(['name' => $name, 'slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('programs', fn (Blueprint $t) => $t->dropColumn(['relation', 'allows_coupons']));
    }
};
