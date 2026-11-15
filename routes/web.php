<?php

use App\Http\Controllers\PilotEnquiryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('home');
Route::view('/confidentialitate', 'privacy')->name('privacy');
Route::post('/program-pilot', PilotEnquiryController::class)
    ->middleware('throttle:pilot')->name('pilot.store');

Route::get('/robots.txt', function () {
    $content = config('gnosix.indexable')
        ? "User-agent: *\nAllow: /\nSitemap: ".config('gnosix.canonical_url')."/sitemap.xml\n"
        : "User-agent: *\nDisallow: /\n";

    return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/sitemap.xml', fn () => response()->view('sitemap')
    ->header('Content-Type', 'application/xml; charset=UTF-8'));
