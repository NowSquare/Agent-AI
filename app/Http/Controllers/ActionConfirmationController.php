<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Services\ActionDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActionConfirmationController extends Controller
{
    /**
     * Show the action confirmation page.
     */
    public function show(Request $request, string $actionId)
    {
        $action = Action::findOrFail($actionId);

        // Check if action has expired
        if ($action->expires_at && $action->expires_at->isPast()) {
            Log::warning('Expired action confirmation attempt', [
                'action_id' => $actionId,
                'expires_at' => $action->expires_at,
            ]);

            return view('action.expired', [
                'action' => $action,
            ]);
        }

        // Check if action is already processed
        if (in_array($action->status, ['completed', 'failed'])) {
            return view('action.already-processed', [
                'action' => $action,
            ]);
        }

        return view('action.confirm', [
            'action' => $action,
            'thread' => $action->thread,
        ]);
    }

    /**
     * Confirm and execute the action.
     */
    public function confirm(Request $request, string $actionId, ActionDispatcher $dispatcher)
    {
        $action = Action::findOrFail($actionId);

        // Check if action has expired
        if ($action->expires_at && $action->expires_at->isPast()) {
            return response()->json([
                'error' => 'Action has expired',
            ], 410);
        }

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
                'message' => 'Action confirmed and executed',
                'action_id' => $action->id,
                'status' => $action->status,
            ]);

        } catch (\Throwable $e) {
            Log::error('Action confirmation failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Action execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
