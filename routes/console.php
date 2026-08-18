<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-timeout sesi CBT yang melewati batas waktu pengerjaan.
Schedule::command('cbt:auto-timeout')->everyMinute();
