<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| API integration note: this project currently uses browser-facing Blade and
| Livewire pages only. Future external API clients should live behind dedicated
| services or API routes instead of being mixed into these web routes.
|
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('products', 'pages::products')->name('products.index');
    Route::livewire('orders', 'pages::orders')->name('orders.index');
    Route::livewire('reports', 'pages::reports')->name('reports.index');
    Route::livewire('logs', 'pages::logs')->name('logs.index');
});

require __DIR__.'/settings.php';
