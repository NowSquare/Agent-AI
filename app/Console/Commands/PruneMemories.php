<?php

namespace App\Console\Commands;

use App\Models\Memory;
use App\Services\MemoryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneMemories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memories:prune 
        {--dry-run : Show what would be pruned without actually deleting}
        {--force : Skip confirmation in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired and low-relevance memories';

    /**
     * Execute the console command.
     */
    public function handle(MemoryService $memoryService): int
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Safety check for production
        if (app()->environment('production') && !$force && !$isDryRun) {
            if (!$this->confirm('Are you sure you want to prune memories in production?')) {
                return Command::FAILURE;
            }
        }

        $batchSize = config('memory.pruning.batch_size', 1000);
        $minAgeDays = config('memory.pruning.min_age_days', 7);
        $minScore = config('memory.pruning.min_score', 0.2);

        $this->info('Starting memory pruning...');
        
        // Step 1: Delete expired memories (except legal category)
        $expiredQuery = Memory::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('ttl_category', '!=', Memory::TTL_LEGAL)
            ->limit($batchSize);

        $expiredCount = $expiredQuery->count();
        
        if ($expiredCount > 0) {
            $this->info("Found {$expiredCount} expired memories to prune");
            
            if (!$isDryRun) {
                $expiredQuery->delete();
                $this->info('Expired memories pruned successfully');
            }
        }

        // Step 2: Find low-relevance memories
        $cutoffDate = now()->subDays($minAgeDays);
        $lowRelevanceMemories = Memory::where('created_at', '<', $cutoffDate)
            ->where('ttl_category', '!=', Memory::TTL_LEGAL)
            ->get()
            ->filter(function ($memory) use ($memoryService, $minScore) {
                return $memoryService->calculateScore($memory) < $minScore;
            })
            ->take($batchSize);

        $lowRelevanceCount = $lowRelevanceMemories->count();

        if ($lowRelevanceCount > 0) {
            $this->info("Found {$lowRelevanceCount} low-relevance memories to prune");
            
            if (!$isDryRun) {
                foreach ($lowRelevanceMemories as $memory) {
                    $memory->delete();
                }
                $this->info('Low-relevance memories pruned successfully');
            }
        }

        // Log statistics
        $stats = [
            'expired_count' => $expiredCount,
            'low_relevance_count' => $lowRelevanceCount,
            'dry_run' => $isDryRun,
            'min_age_days' => $minAgeDays,
            'min_score' => $minScore,
            'batch_size' => $batchSize,
        ];

        Log::info('Memory pruning completed', $stats);
        
        $this->table(
            ['Metric', 'Value'],
            collect($stats)->map(fn($v, $k) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : $v])->toArray()
        );

        return Command::SUCCESS;
    }
}
