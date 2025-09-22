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

    /**
     * Cancel the action.
     */
    public function cancel(Request $request, string $actionId)
    {
        $action = Action::findOrFail($actionId);

        // Check if action has expired
        if ($action->expires_at && $action->expires_at->isPast()) {
            Log::warning('Expired action cancel attempt', [
                'action_id' => $actionId,
                'expires_at' => $action->expires_at,
            ]);

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

        // Cancel the action
        $action->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        Log::info('Action cancelled via clarification email', [
            'action_id' => $action->id,
            'action_type' => $action->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Action cancelled',
            'action_id' => $action->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Handle option selection from options email.
     */
    public function chooseOption(Request $request, string $actionId, string $key, ActionDispatcher $dispatcher)
    {
        $action = Action::findOrFail($actionId);

        // Check if action has expired
        if ($action->expires_at && $action->expires_at->isPast()) {
            Log::warning('Expired option selection attempt', [
                'action_id' => $actionId,
                'option_key' => $key,
                'expires_at' => $action->expires_at,
            ]);

            return response()->view('action.expired', [
                'action' => $action,
            ])->setStatusCode(410);
        }

        // Check if action is already processed
        if (in_array($action->status, ['completed', 'failed', 'cancelled'])) {
            return response()->view('action.already-processed', [
                'action' => $action,
            ])->setStatusCode(409);
        }

        // Update action based on selected option
        $this->updateActionFromOption($action, $key);

        // Auto-dispatch for safe actions, or set for confirmation
        if ($this->shouldAutoDispatchOption($key)) {
            try {
                $dispatcher->dispatch($action);

                return response()->view('action.confirmed', [
                    'action' => $action,
                    'message' => 'Your selection has been processed automatically.',
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto-dispatch after option selection failed', [
                    'action_id' => $action->id,
                    'option_key' => $key,
                    'error' => $e->getMessage(),
                ]);

                return response()->view('action.error', [
                    'action' => $action,
                    'error' => 'Processing failed. Please try again.',
                ])->setStatusCode(500);
            }
        } else {
            // Set for manual confirmation
            $action->update(['status' => 'awaiting_confirmation']);

            return response()->view('action.confirm', [
                'action' => $action,
                'thread' => $action->thread,
                'message' => 'Please confirm your selection.',
            ]);
        }
    }

    /**
     * Update action parameters based on selected option.
     */
    private function updateActionFromOption(Action $action, string $key): void
    {
        $originalQuestion = $action->payload_json['question'] ?? '';

        switch ($key) {
            case 'italian_pasta':
                $action->update([
                    'type' => 'info_request',
                    'payload_json' => [
                        'question' => 'Can you provide an Italian pasta recipe for 4 people?',
                        'original_question' => $originalQuestion,
                        'clarified_via' => 'options_email',
                    ],
                ]);
                break;

            case 'greek_salad':
                $action->update([
                    'type' => 'info_request',
                    'payload_json' => [
                        'question' => 'Can you provide a Greek salad recipe for 4 people?',
                        'original_question' => $originalQuestion,
                        'clarified_via' => 'options_email',
                    ],
                ]);
                break;

            case 'something_else':
                $action->update([
                    'type' => 'info_request',
                    'payload_json' => [
                        'question' => $originalQuestion, // Keep original for manual clarification
                        'needs_manual_clarification' => true,
                        'clarified_via' => 'options_email',
                    ],
                ]);
                break;

            case 'general_help':
            case 'specific_question':
            case 'clarify_request':
            default:
                // Keep original question but mark as clarified
                $action->update([
                    'payload_json' => array_merge($action->payload_json, [
                        'clarified_via' => 'options_email',
                        'selected_option' => $key,
                    ]),
                ]);
                break;
        }
    }

    /**
     * Determine if an option should auto-dispatch or require confirmation.
     */
    private function shouldAutoDispatchOption(string $key): bool
    {
        // Auto-dispatch safe, informational requests
        return in_array($key, ['italian_pasta', 'greek_salad', 'general_help']);
    }
}
