<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\RevenueStreamController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TiqetsSyncController;
use App\Http\Controllers\AdSenseSyncController;
use App\Http\Controllers\TradeTrackerSyncController;
use App\Services\CommentSyncService;

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
Route::delete('imports-by-month', [ImportController::class, 'destroyByMonth'])->name('imports.destroyByMonth');

Route::get('/tiqets/sync', [TiqetsSyncController::class, 'index'])->name('tiqets.sync');
Route::post('/tiqets/sync', [TiqetsSyncController::class, 'sync'])->name('tiqets.sync.run');
Route::post('/tiqets/sync/clear-cache', [TiqetsSyncController::class, 'clearCache'])->name('tiqets.sync.clear-cache');
Route::post('/tiqets/sync/day', [TiqetsSyncController::class, 'syncDay'])->name('tiqets.sync.day');
Route::post('/tiqets/sync/fix-day', [TiqetsSyncController::class, 'fixDay'])->name('tiqets.sync.fix-day');

Route::get('/adsense/sync', [AdSenseSyncController::class, 'index'])->name('adsense.sync');
Route::get('/adsense/callback', [AdSenseSyncController::class, 'callback'])->name('adsense.callback');
Route::post('/adsense/disconnect', [AdSenseSyncController::class, 'disconnect'])->name('adsense.disconnect');
Route::post('/adsense/sync/run', [AdSenseSyncController::class, 'sync'])->name('adsense.sync.run');
Route::post('/adsense/sync/day', [AdSenseSyncController::class, 'syncDay'])->name('adsense.sync.day');
Route::post('/adsense/sync/fix-day', [AdSenseSyncController::class, 'fixDay'])->name('adsense.fix-day');

Route::get('/tradetracker/sync', [TradeTrackerSyncController::class, 'index'])->name('tradetracker.sync');
Route::post('/tradetracker/sync/run', [TradeTrackerSyncController::class, 'sync'])->name('tradetracker.sync.run');
Route::post('/tradetracker/sync/day', [TradeTrackerSyncController::class, 'syncDay'])->name('tradetracker.sync.day');
Route::post('/tradetracker/fix-day', [TradeTrackerSyncController::class, 'fixDay'])->name('tradetracker.fix-day');


Route::get('/fetch-comments', [CommentController::class, 'fetchComments']);
Route::get('/generate-responses', [CommentController::class, 'generateResponse']);
Route::get('/comments/translate', [CommentController::class, 'translate']);


Route::get('/comments', [CommentController::class, 'index'])->name('comments.index');
Route::get('/comments/{comment}', [CommentController::class, 'show'])->name('comments.show');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
Route::put('/comments/{comment}/translate', [CommentController::class, 'submitTranslation'])->name('comments.submitTranslation');
Route::put('/comments/{comment}/submitTranslatedReply', [CommentController::class, 'submitTranslatedReply'])->name('comments.submitTranslatedReply');


Route::post('/comments/', [CommentController::class, 'store'])->name('comments.store');

Route::post('/comments/{comment}/approve', [CommentController::class, 'approve'])->name('comments.approve');
Route::post('/comments/{comment}/reject', [CommentController::class, 'reject'])->name('comments.reject');


Route::get('/search-google', [SearchController::class, 'searchGoogle'])->name('search-google');
Route::post('/blacklist/add', [SearchController::class, 'addToBlacklist'])->name('blacklist.add');

require __DIR__.'/auth.php';
