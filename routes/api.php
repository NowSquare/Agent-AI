<?php

use App\Http\Controllers\Api\ActionController;
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

// TODO: MCP endpoints
// Route::any('/mcp/agent', [McpController::class, 'handle'])->name('api.mcp.agent');
