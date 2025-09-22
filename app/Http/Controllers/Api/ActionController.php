<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Services\ActionDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActionController extends Controller
{
    /**
     * Dispatch an action for execution.
     */
    public function dispatch(Request $request, ActionDispatcher $dispatcher): JsonResponse
    {
        $request->validate([
            'action_id' => 'required|ulid|exists:actions,id',
        ]);

        $action = Action::findOrFail($request->action_id);

        // Check if action is already processed
        if (in_array($action->status, ['completed', 'failed'])) {
            return response()->json([
                'error' => 'Action already processed',
                'status' => $action->status,
            ], 409);
        }

        try {
            $dispatcher->dispatch($action);

            return response()->json([
                'success' => true,
                'action_id' => $action->id,
                'status' => $action->status,
                'message' => 'Action dispatched successfully',
            ]);

        } catch (\Throwable $e) {
            Log::error('Action dispatch failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Action dispatch failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
