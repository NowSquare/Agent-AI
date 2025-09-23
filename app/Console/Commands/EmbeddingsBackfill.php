<?php

namespace App\Console\Commands;

use App\Services\Embeddings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbeddingsBackfill extends Command
{
    protected $signature = 'embeddings:backfill {--since=} {--limit=1000} {--dry-run}';

    protected $description = 'Backfill pgvector embeddings for email bodies, attachment texts, and memories';

    public function handle(Embeddings $embeddings): int
    {
        $since = $this->option('since');
        $limit = (int) $this->option('limit');
        $dry   = (bool) $this->option('dry-run');
        $dim   = (int) config('llm.embeddings.dim', 1024);

        $this->info("Backfill start (since=".($since ?: 'ALL').", limit={$limit}, dry={$dry})");

        $totals = ['processed'=>0,'embedded'=>0,'skipped'=>0,'failed'=>0];

        // email_messages
        $this->processTable(
            table: 'email_messages',
            id: 'id',
            textCol: 'body_text',
            vecCol: 'body_embedding',
            sinceCol: 'created_at',
            dim: $dim,
            limit: $limit,
            dry: $dry,
            svc: $embeddings,
            totals: $totals
        );

        // attachment_extractions
        $this->processTable(
            table: 'attachment_extractions',
            id: 'id',
            textCol: 'text_excerpt',
            vecCol: 'text_embedding',
            sinceCol: 'created_at',
            dim: $dim,
            limit: $limit,
            dry: $dry,
            svc: $embeddings,
            totals: $totals
        );

        // memories
        $this->processTable(
            table: 'memories',
            id: 'id',
            textCol: 'value_json', // stored as jsonb; cast to text
            vecCol: 'content_embedding',
            sinceCol: 'created_at',
            dim: $dim,
            limit: $limit,
            dry: $dry,
            svc: $embeddings,
            totals: $totals,
            isJson: true
        );

        $this->newLine();
        $this->info("Summary: processed={$totals['processed']} embedded={$totals['embedded']} skipped={$totals['skipped']} failed={$totals['failed']}");

        return self::SUCCESS;
    }

    private function processTable(string $table, string $id, string $textCol, string $vecCol, string $sinceCol, int $dim, int $limit, bool $dry, Embeddings $svc, array &$totals, bool $isJson=false): void
    {
        $this->line("→ {$table}.{$vecCol}");
        $q = DB::table($table)->select($id, $textCol, $vecCol, $sinceCol)->whereNull($vecCol);
        if ($since = $this->option('since')) {
            $q->whereDate($sinceCol, '>=', $since);
        }
        $q->orderBy($sinceCol)->limit($limit);

        $q->chunkById(200, function($rows) use ($table, $id, $textCol, $vecCol, $dim, $dry, $svc, &$totals, $isJson) {
            foreach ($rows as $r) {
                $totals['processed']++;
                $text = $isJson ? (is_string($r->{$textCol}) ? $r->{$textCol} : json_encode($r->{$textCol})) : ($r->{$textCol} ?? '');
                $text = (string) $text;
                if (trim($text) === '') {
                    $totals['skipped']++;
                    continue;
                }
                $vec = $svc->embedText($text);
                if (count($vec) !== $dim) {
                    $totals['failed']++;
                    Log::warning('Embedding dim mismatch', ['table'=>$table,'id'=>$r->{$id},'expected'=>$dim,'got'=>count($vec)]);
                    continue;
                }
                if ($dry) {
                    $totals['embedded']++;
                    $this->line("DRY-RUN: would store vector for {$table} {$r->{$id}}");
                    continue;
                }
                $literal = '['.implode(',', $vec).']';
                DB::statement("UPDATE {$table} SET {$vecCol}=?::vector WHERE {$id}=?", [$literal, $r->{$id}]);
                $totals['embedded']++;
            }
        }, $id);
    }
}


