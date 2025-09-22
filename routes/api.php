<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\MemoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Action dispatching (internal API)
Route::post('/actions/dispatch', [ActionController::class, 'dispatch'])->name('api.actions.dispatch');

// Memory management endpoints
Route::prefix('memories')->name('api.memories.')->group(function () {
    // Preview memories (dev-only or API token)
    Route::get('/preview', [MemoryController::class, 'preview'])
        ->name('preview');

    // Forget memories (signed URL or API token)
    Route::post('/forget', [MemoryController::class, 'forget'])
        ->name('forget')
        ->middleware('signed');
});
