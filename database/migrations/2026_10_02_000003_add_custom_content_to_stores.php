<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conținut unic per magazin (completat manual sau de un script / AI): fără texte identice între pagini. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->text('offers_text')->nullable();   // paragraf imediat sub lista de oferte
            $t->json('howto')->nullable();         // [{t, p}] „Cum folosești codurile”
            $t->json('tips')->nullable();          // [{t, p}] „Cum economisești”
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn(['offers_text', 'howto', 'tips']));
    }
};
