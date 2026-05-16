<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\TiqetsApiService;
use App\Models\RevenueStream;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $revenueStream = RevenueStream::where('title', 'like', '%iqets%')->first();
    if ($revenueStream) {
        app(TiqetsApiService::class)->syncDate(now()->subDay()->toDateString(), $revenueStream->id);
    }
})->dailyAt('06:00')->name('tiqets-daily-sync')->withoutOverlapping();
