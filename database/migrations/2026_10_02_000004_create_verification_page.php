<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/** „Metodologie” (goală) devine pagina de încredere /cum-verificam-codurile, cu conținut complet (editabil din admin). */
return new class extends Migration
{
    public function up(): void
    {
        $body = (string) file_get_contents(resource_path('content/cum-verificam-codurile.html'));
        $page = Page::where('slug', 'metodologie')->first() ?? Page::firstOrNew(['slug' => 'cum-verificam-codurile']);
        $page->fill(['slug' => 'cum-verificam-codurile', 'title' => 'Cum verificăm codurile', 'in_footer' => true]);
        if (trim(strip_tags((string) $page->body)) === '') {
            $page->body = $body;
        }
        $page->sort ??= 2;
        $page->save();
    }

    public function down(): void
    {
        Page::where('slug', 'cum-verificam-codurile')->update(['slug' => 'metodologie', 'title' => 'Metodologie']);
    }
};
