<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Agendamento da coleta de preços
|--------------------------------------------------------------------------
| Roda todo dia às 06:00 (horário do servidor). withoutOverlapping evita
| que duas coletas rodem ao mesmo tempo se uma demorar. runInBackground
| libera o scheduler enquanto a coleta processa.
|
| No VPS, basta 1 linha no crontab:
|   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('app:menor-preco-sync')
    ->dailyAt('06:00')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Coleta diária (app:menor-preco-sync) falhou.');
    });
