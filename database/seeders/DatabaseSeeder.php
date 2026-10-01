<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Date inițiale: etichetele tematice, paginile statice (goale, le completezi din admin) și contul de admin.
 * Contul de admin local: ADMIN_EMAIL / ADMIN_PASSWORD din .env (vezi .env.example). Pe producție schimbă parola imediat.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['transport-gratuit' => 'Transport gratuit', 'prima-comanda' => 'Prima comandă', 'studenti' => 'Studenți', 'cumulabil' => 'Cumulabil'] as $slug => $name) {
            Tag::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $pages = [
            'despre-noi' => 'Despre noi',
            'cum-functioneaza' => 'Cum funcționează',
            'cum-verificam-codurile' => 'Cum verificăm codurile',
            'transparenta-si-afiliere' => 'Transparență și afiliere',
            'calendar-reduceri' => 'Calendar reduceri',
            'contact' => 'Contact',
            'termeni-si-conditii' => 'Termeni și condiții',
            'confidentialitate' => 'Confidențialitate',
            'cookies' => 'Cookies',
        ];
        $i = 0;
        foreach ($pages as $slug => $title) {
            $body = $slug === 'cum-verificam-codurile' ? (string) file_get_contents(resource_path('content/cum-verificam-codurile.html')) : '';
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'sort' => $i++]);
        }

        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cupoane.test')],
            ['name' => 'Admin', 'password' => bcrypt(env('ADMIN_PASSWORD', 'password'))]
        );
    }
}
