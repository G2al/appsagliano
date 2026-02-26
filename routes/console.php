<?php

use App\Models\Maintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maintenance:check-due-alerts', function () {
    $count = Maintenance::notifyDueDateAlerts();
    $this->info("Notifiche manutenzione inviate: {$count}");
})->purpose('Invia alert manutenzione per scadenze a data');

Schedule::command('maintenance:check-due-alerts')
    ->everyFiveSeconds()
    ->withoutOverlapping();
