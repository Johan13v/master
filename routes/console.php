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

Schedule::call(function () {
    $service = app(\App\Services\AdSenseApiService::class);
    if (!$service->isConnected()) return;
    $revenueStream = \App\Models\RevenueStream::where('title', 'like', '%adsense%')
        ->orWhere('title', 'like', '%google%')->first();
    if ($revenueStream) {
        $service->syncDate(now()->subDay()->toDateString(), $revenueStream->id);
    }
})->dailyAt('06:05')->name('adsense-daily-sync')->withoutOverlapping();
