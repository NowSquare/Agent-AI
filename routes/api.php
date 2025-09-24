<?php
/**
 * What this file does — Declares JSON API routes used by the UI and internal tools.
 * Plain: The backend endpoints that the app calls in the background.
 * For engineers: Keep destructive actions signed or authenticated; namespace routes clearly.
 */

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AgentSpecializationController;
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

// Agent specialization management
Route::apiResource('agent-specializations', AgentSpecializationController::class)
    ->names([
        'index' => 'api.agent-specializations.index',
        'store' => 'api.agent-specializations.store',
        'show' => 'api.agent-specializations.show',
        'update' => 'api.agent-specializations.update',
        'destroy' => 'api.agent-specializations.destroy',
    ])
    ->middleware('auth');
