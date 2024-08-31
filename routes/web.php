<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\RevenueStreamController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource('websites', WebsiteController::class);
Route::resource('imports', ImportController::class);
Route::resource('revenue-streams', RevenueStreamController::class);
Route::resource('cities', CityController::class);
Route::resource('commissions', CommissionController::class);


Route::get('revenue-streams/{revenueStream}/import', [ImportController::class, 'showImportForm'])->name('imports.showForm');
Route::post('revenue-streams/{revenueStream}/import', [ImportController::class, 'import'])->name('imports.import');
Route::post('revenue-streams/{revenueStream}/import/update-matchers', [ImportController::class, 'updateMatchers'])->name('imports.updateMatchers');
Route::delete('imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');


require __DIR__.'/auth.php';
