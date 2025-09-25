<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class ClearAll extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'clear:all {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Clear all caches, queues, Redis data, reset database, and clear logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will completely reset your development environment. Continue? (yes/no)')) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->info('1) Clearing application optimizations and caches...');
        try {
            // optimize:clear removes config, route, view caches and compiled classes
            $this->callSilent('optimize:clear');
            $this->info('   ✓ optimize:clear completed');
        } catch (\Throwable $e) {
            $this->error('   ✗ optimize:clear failed: '.$e->getMessage());
        }

        $this->info('2) Clearing queued jobs...');
        try {
            $this->callSilent('queue:clear');
            $this->info('   ✓ queue:clear completed');
        } catch (\Throwable $e) {
            $this->error('   ✗ queue:clear failed: '.$e->getMessage());
        }

        $this->info('3) Clearing Horizon state...');
        try {
            $this->callSilent('horizon:clear');
            $this->info('   ✓ horizon:clear completed');
        } catch (\Throwable $e) {
            $this->error('   ✗ horizon:clear failed: '.$e->getMessage());
        }

        $this->info('4) Flushing all Redis data (FLUSHALL)...');
        try {
            // Prefer PhpRedis client flushAll when available
            try {
                $client = Redis::connection()->client();
                if (method_exists($client, 'flushAll')) {
                    $client->flushAll();
                } else {
                    // Fallback to direct command
                    Redis::command('flushall');
                }
            } catch (\Throwable $inner) {
                // Fallback to command if client approach fails
                Redis::command('flushall');
            }
            $this->info('   ✓ Redis FLUSHALL completed');
        } catch (\Throwable $e) {
            $this->error('   ✗ Redis FLUSHALL failed: '.$e->getMessage());
        }

        $this->info('5) Resetting database (migrate:fresh --seed)...');
        try {
            $this->callSilent('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
            $this->info('   ✓ Database reset and seed completed');
        } catch (\Throwable $e) {
            $this->error('   ✗ Database reset failed: '.$e->getMessage());
        }

        $this->info('6) Clearing application logs...');
        try {
            $logDir = storage_path('logs');
            if (File::exists($logDir)) {
                foreach (File::files($logDir) as $file) {
                    // Only empty regular files; skip directories or special files
                    if ($file->isFile()) {
                        // Empty the file contents instead of deleting
                        File::put($file->getPathname(), '');
                    }
                }
            }
            $this->info('   ✓ Logs cleared');
        } catch (\Throwable $e) {
            $this->error('   ✗ Failed to clear logs: '.$e->getMessage());
        }

        $this->line('');
        $this->info('All steps attempted. Your development environment has been reset.');

        return self::SUCCESS;
    }
}
