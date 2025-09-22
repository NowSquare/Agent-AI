<?php

namespace App\Services;

use App\Models\Action;
use App\Services\Coordinator;
use Illuminate\Support\Facades\Log;

class ActionDispatcher
{
    public function __construct(
        private Coordinator $coordinator,
    ) {}

    /**
     * Dispatch an action for execution.
     */
    public function dispatch(Action $action): void
    {
        Log::info('ActionDispatcher: Dispatching action to coordinator', [
            'action_id' => $action->id,
            'type' => $action->type,
            'thread_id' => $action->thread_id,
        ]);

        try {
            // Delegate to coordinator for agent-based processing
            $this->coordinator->processAction($action);

            Log::info('ActionDispatcher: Action processed successfully via coordinator', [
                'action_id' => $action->id,
                'final_status' => $action->status,
            ]);

        } catch (\Throwable $e) {
            Log::error('ActionDispatcher: Action processing failed', [
                'action_id' => $action->id,
                'type' => $action->type,
                'error' => $e->getMessage(),
            ]);

            // Ensure action is marked as failed
            $action->update([
                'status' => 'failed',
                'error_json' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

}
