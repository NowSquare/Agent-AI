<?php

use App\Http\Controllers\Auth\ChallengeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerifyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Webhook\PostmarkInboundController;
use App\Mcp\Servers\AgentAiServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

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

// Signed action links (public, no auth required)
Route::get('/a/{action}', [App\Http\Controllers\ActionConfirmationController::class, 'show'])->name('action.confirm.show');
Route::post('/a/{action}', [App\Http\Controllers\ActionConfirmationController::class, 'confirm'])->name('action.confirm');
Route::post('/a/{action}/cancel', [App\Http\Controllers\ActionConfirmationController::class, 'cancel'])->name('action.confirm.cancel');

// Options selection routes
Route::get('/a/{action}/choose/{key}', [App\Http\Controllers\ActionConfirmationController::class, 'chooseOption'])->name('action.options.choose');

// Signed attachment downloads
Route::get('/attachments/{attachment}', [App\Http\Controllers\AttachmentDownloadController::class, 'show'])
    ->name('attachments.show')
    ->middleware('signed');

// Webhooks (exclude from CSRF protection)
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/webhooks/inbound-email', PostmarkInboundController::class);
});

// MCP Server routes
Route::prefix('mcp')->group(function () {
    Mcp::web('/ai', AgentAiServer::class)->name('mcp.ai');
});
