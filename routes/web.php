<?php

use App\Http\Controllers\Webhook\PostmarkInboundController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Webhooks
Route::post('/webhooks/inbound-email', PostmarkInboundController::class);
