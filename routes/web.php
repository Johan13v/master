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
