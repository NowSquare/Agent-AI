<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgetMemoryRequest;
use App\Http\Requests\PreviewMemoryRequest;
use App\Models\Memory;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MemoryController extends Controller
{
    public function __construct(
        private MemoryService $memoryService
    ) {}

    /**
     * Preview memories for a given scope and ID.
     * This endpoint is dev-only and requires a valid API token.
     */
    public function preview(PreviewMemoryRequest $request): JsonResponse
    {
        try {
            $memories = $this->memoryService->retrieve(
                scope: $request->validated('scope'),
                scopeId: $request->validated('scope_id'),
                key: $request->validated('key'),
                limit: $request->validated('limit', 10)
            );

            return response()->json([
                'success' => true,
                'data' => $memories->map(fn($memory) => [
                    'id' => $memory->id,
                    'key' => $memory->key,
                    'value' => $memory->value_json,
                    'confidence' => $memory->confidence,
                    'ttl_category' => $memory->ttl_category,
                    'score' => $this->memoryService->calculateScore($memory),
                    'created_at' => $memory->created_at,
                    'last_used_at' => $memory->last_used_at,
                    'usage_count' => $memory->usage_count,
                ]),
            ]);

        } catch (\Throwable $e) {
            Log::error('Memory preview failed', [
                'error' => $e->getMessage(),
                'scope' => $request->validated('scope'),
                'scope_id' => $request->validated('scope_id'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to preview memories',
            ], 500);
        }
    }

    /**
     * Forget (soft delete) memories by ID or scope.
     * This endpoint requires a signed URL or valid API token.
     */
    public function forget(ForgetMemoryRequest $request): JsonResponse
    {
        try {
            $query = Memory::query();

            // Filter by ID if provided
            if ($id = $request->validated('id')) {
                $query->where('id', $id);
            }

            // Filter by scope if provided
            if ($scope = $request->validated('scope')) {
                $query->where('scope', $scope);

                if ($scopeId = $request->validated('scope_id')) {
                    $query->where('scope_id', $scopeId);
                }
            }

            // Perform soft delete
            $count = $query->count();
            $query->delete();

            Log::info('Memories forgotten', [
                'count' => $count,
                'id' => $id,
                'scope' => $scope,
                'scope_id' => $scopeId,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'forgotten_count' => $count,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Memory forget failed', [
                'error' => $e->getMessage(),
                'id' => $request->validated('id'),
                'scope' => $request->validated('scope'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to forget memories',
            ], 500);
        }
    }
}
