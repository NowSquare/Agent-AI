<?php

use App\Http\Controllers\Auth\ChallengeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerifyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Webhook\PostmarkInboundController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authentication
Route::get('/auth/challenge', function () {
    return view('auth.challenge');
})->name('auth.challenge.form');

Route::get('/auth/verify', function () {
    return view('auth.verify');
})->name('auth.verify.form');

Route::post('/auth/challenge', ChallengeController::class)->name('auth.challenge');
Route::post('/auth/verify', VerifyController::class)->name('auth.verify');
Route::get('/login/{token}', [LoginController::class, 'magicLink'])->name('login.magic');

// Dashboard (protected)
Route::middleware('auth')->get('/dashboard', DashboardController::class)->name('dashboard');

// Webhooks
Route::post('/webhooks/inbound-email', PostmarkInboundController::class);
