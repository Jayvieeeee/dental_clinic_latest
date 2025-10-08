<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing/Welcome', [

    ]);
});

Route::get('/services', fn() => Inertia::render('Landing/Services'));
Route::get('/faqs', fn() => Inertia::render('Landing/Faqs'));
Route::get('/contactUs', fn() => Inertia::render('Landing/ContactUs'));
Route::get('/testimonials', fn() => Inertia::render('Landing/Testimonials'));

Route::get('/home', fn() => Inertia::render('Customer/Home'));

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
