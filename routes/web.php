<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';

Route::view('/', 'landing.index')->name('home');
Route::view('/en', 'landing.index')->name('home.en');
Route::view('/confidentialitate', 'privacy')->name('privacy');
Route::view('/en/privacy', 'privacy')->name('privacy.en');
Route::post('/contact', ContactController::class)->middleware('throttle:pilot')->name('contact.store');
Route::post('/en/contact', ContactController::class)->middleware('throttle:pilot')->name('contact.store.en');
Route::post('/program-pilot', ContactController::class)
    ->middleware('throttle:pilot')->name('pilot.store');

Route::get('/robots.txt', function () {
    $content = \App\Support\SiteSettings::value('indexable')
        ? "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: ".\App\Support\SiteSettings::value('canonical_url')."/sitemap.xml\n"
        : "User-agent: *\nDisallow: /\n";

    return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/sitemap.xml', fn () => response()->view('sitemap')
    ->header('Content-Type', 'application/xml; charset=UTF-8'));
