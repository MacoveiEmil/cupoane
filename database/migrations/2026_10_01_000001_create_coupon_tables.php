<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelul de date al site-ului de cupoane.
 * „Activ” = status published, starts_on ≤ azi (sau gol), expires_on ≥ azi (sau gol). Zilele sunt în fusul Europe/Bucharest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('logo_path')->nullable();      // logo WebP în storage/app/public/logos
            $t->string('logo_src')->nullable();       // URL logo din rețea, de descărcat la import
            $t->string('url')->nullable();            // site-ul magazinului
            $t->string('affiliate_url', 2048)->nullable();
            $t->string('network', 20)->nullable();    // 2performant | profitshare | direct
            $t->string('program_id', 64)->nullable();
            $t->text('description')->nullable();      // HTML simplu
            $t->json('info')->nullable();             // informații utile (transport, retur…)
            $t->json('faq')->nullable();              // [{q, a}]
            $t->unsignedSmallInteger('home_order')->default(0); // 0 = nu apare în „Magazine populare”
            $t->date('updated_on')->nullable();       // „Actualizat la”
            $t->timestamps();
            $t->index(['network', 'program_id']);
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('category_store', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'store_id']);
        });

        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('code', 64)->nullable();               // gol = ofertă fără cod
            $t->decimal('value', 8, 2)->nullable();
            $t->string('value_type', 12)->default('procent'); // procent | lei | transport
            $t->boolean('upto')->default(false);              // „până la”
            $t->text('terms')->nullable();
            $t->string('affiliate_url', 2048)->nullable();    // override; gol = linkul magazinului
            $t->date('starts_on')->nullable();
            $t->date('expires_on')->nullable();               // inclusiv
            $t->boolean('exclusive')->default(false);
            $t->boolean('top')->default(false);
            $t->date('verified_on')->nullable();
            $t->string('status', 12)->default('draft');       // draft | published
            $t->boolean('needs_recheck')->default(false);
            $t->string('ext_id', 100)->nullable()->unique();  // „retea:id” (deduplicare la import)
            $t->timestamps();
            $t->softDeletes();                                // coșul de gunoi (golit după 30 de zile)
            $t->index(['status', 'expires_on', 'starts_on']);
        });

        Schema::create('coupon_tag', function (Blueprint $t) {
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['coupon_id', 'tag_id']);
        });

        Schema::create('deals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('product_url', 2048);
            $t->string('deeplink', 2048)->nullable();
            $t->string('image_path')->nullable();
            $t->decimal('price_old', 10, 2)->nullable();
            $t->decimal('price_new', 10, 2)->nullable();
            $t->unsignedTinyInteger('percent')->default(0);
            $t->date('expires_on')->nullable();
            $t->date('verified_on')->nullable();
            $t->string('status', 12)->default('draft');
            $t->boolean('needs_recheck')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'expires_on']);
        });

        // Agregate zilnic prin UPSERT.
        Schema::create('clicks', function (Blueprint $t) {
            $t->unsignedTinyInteger('object_type'); // 1 cupon, 2 deal, 3 magazin
            $t->unsignedBigInteger('object_id');
            $t->date('date');
            $t->unsignedInteger('count')->default(0);
            $t->primary(['object_type', 'object_id', 'date']);
            $t->index('date');
        });

        Schema::create('votes', function (Blueprint $t) {
            $t->unsignedBigInteger('coupon_id');
            $t->date('date');
            $t->unsignedInteger('yes')->default(0);
            $t->unsignedInteger('no')->default(0);
            $t->primary(['coupon_id', 'date']);
        });

        Schema::create('vote_guards', function (Blueprint $t) {
            $t->char('hash', 64)->primary();        // HMAC(ip|cupon|zi), IP-ul nu se stochează
            $t->timestamp('created_at')->index();
        });

        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('network', 20);
            $t->string('program_id', 64);
            $t->string('name')->default('');
            $t->string('site_url')->default('');
            $t->string('unique_code', 64)->default('');
            $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('accepted')->nullable();     // null = necunoscut
            $t->timestamp('last_seen')->nullable();
            $t->unique(['network', 'program_id']);
        });

        // Pagini statice + texte editabile ale site-ului.
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->longText('body')->nullable();
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->boolean('in_footer')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();           // valorile secrete sunt criptate (APP_KEY)
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'pages', 'programs', 'vote_guards', 'votes', 'clicks', 'deals', 'coupon_tag', 'coupons', 'tags', 'category_store', 'categories', 'stores'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
