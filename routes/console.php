<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agenda a verificação de promissórias próximas do vencimento diariamente às 8h
Schedule::command('promissorias:verificar-vencimento --dias=3')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo');
