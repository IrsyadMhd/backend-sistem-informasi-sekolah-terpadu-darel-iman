<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-timeout sesi CBT yang melewati batas waktu pengerjaan.
Schedule::command('cbt:auto-timeout')->everyMinute();

// Auto-close sesi KBM yang belum ditutup pada sore hari dan akhir hari
Schedule::command('kbm:auto-close-sessions')->dailyAt('17:00');
Schedule::command('kbm:auto-close-sessions')->dailyAt('23:59');
