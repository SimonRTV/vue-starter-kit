<?php

use App\Models\Activity;
use App\Models\CsvImport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [Activity::class]])
    ->daily()->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [CsvImport::class]])
    ->hourly()->withoutOverlapping();
