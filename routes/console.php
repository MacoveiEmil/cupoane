<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Programări. Pe hosting e nevoie de UN singur cron de sistem, la fiecare minut:
 *   * * * * * cd /home/UTILIZATOR/aplicatie && php artisan schedule:run >> /dev/null 2>&1
 */

// Curățarea zilnică, imediat după ce ofertele de ieri au expirat.
Schedule::command('cupons:cleanup')->dailyAt('00:05')->timezone('Europe/Bucharest')->withoutOverlapping();

// Import zilnic din 2Performant (ofertele noi intră ca ciornă, pentru verificare).
Schedule::command('cupons:import --network=2performant')->dailyAt('06:30')->timezone('Europe/Bucharest')->withoutOverlapping();
