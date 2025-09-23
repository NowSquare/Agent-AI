<?php

namespace App\Console\Commands;

use App\Services\Embeddings;
use App\Services\GroundingService;
use App\Services\ModelRouter;
use Illuminate\Console\Command;

class LlmRoutingDryRun extends Command
{
    protected $signature = 'llm:routing-dry-run {--text=} {--k=8}';

    protected $description = 'Show routing plan (CLASSIFY → GROUNDED|SYNTH) without calling the LLM';

    public function handle(Embeddings $embeddings, GroundingService $grounding, ModelRouter $router): int
    {
        $text = $this->option('text');
        if ($text === null) {
            $this->line('Enter text and press Ctrl-D (or Ctrl-Z on Windows) when done:');
            $text = stream_get_contents(STDIN);
        }
        $text = trim((string) $text);
        if ($text === '') {
            $this->error('No text provided.');
            return self::FAILURE;
        }

        $k = (int) $this->option('k');

        // Token estimate (simple heuristic)
        $tokensIn = (int) ceil(str_word_count($text) / 0.75);

        // Embedding + retrieval
        $vec = $embeddings->embedText($text);
        $results = $grounding->retrieveTopK($text, $k);
        $hitRate = $grounding->hitRate($results);
        $topSim  = $grounding->topSimilarity($results);

        // Choose role and resolve provider/model
        $role = $router->chooseRole($tokensIn, $hitRate, $topSim);
        $pm   = $router->resolveProviderModel($role);

        // Output plan
        $this->info('Routing Plan');
        $this->line('-------------------------------------');
        $this->line('Tokens In : '.$tokensIn);
        $this->line('Hit Rate  : '.number_format($hitRate, 3));
        $this->line('Top Sim   : '.number_format($topSim, 3));
        $this->line('Role      : '.$role);
        $this->line('Provider  : '.$pm['provider']);
        $this->line('Model     : '.$pm['model']);
        $this->line('Tools     : '.($pm['tools'] ? 'yes' : 'no'));
        $this->line('Reasoning : '.($pm['reasoning'] ? 'yes' : 'no'));

        // Show top-k snippet ids for transparency
        if (!empty($results)) {
            $this->newLine();
            $this->line('Top-K Snippets:');
            foreach (array_slice($results, 0, $k) as $r) {
                $this->line(sprintf('  [%s:%s] sim=%.3f', $r['src'], $r['id'], $r['similarity']));
            }
        }

        $this->newLine();
        $this->line('Thresholds:');
        $thr = config('llm.routing.thresholds');
        $this->line('  grounding_hit_min      = '.$thr['grounding_hit_min']);
        $this->line('  synth_complexity_tokens= '.$thr['synth_complexity_tokens']);

        return self::SUCCESS;
    }
}


