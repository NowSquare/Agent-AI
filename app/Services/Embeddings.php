<?php
/**
 * What this file does — Turns text into number vectors (embeddings).
 * Plain: We convert words into lists of numbers so the database can search by meaning.
 * How this fits in:
 * - GroundingService asks for vectors to search pgvector columns
 * - Memory writing may embed content for later retrieval
 * - Dimensions must match config/llm.php embeddings.dim
 * Key terms:
 * - embedding: number list representing meaning
 * - dim: how long the list is (e.g., 1024)
 *
 * For engineers:
 * - Inputs: text string
 * - Output: float[] sized to dim
 * - Failure modes: provider error; returns empty/throws if model missing
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Purpose: Create embeddings using the configured provider/model.
 * Responsibilities:
 * - Call provider to embed text
 * - Normalize output to the configured dimension
 * Collaborators: config('llm.embeddings.*') and Llm providers
 */
class Embeddings
{
    public function __construct(private array $config = [])
    {
        $this->config = $config ?: config('llm.embeddings', []);
    }

    /**
     * Create an embedding vector for the given text using the configured provider.
     * Returns a float[] with length equal to config('llm.embeddings.dim').
     */
    public function embedText(string $text): array
    {
        $provider = $this->config['provider'] ?? 'ollama';
        $model = $this->config['model'] ?? 'mxbai-embed-large';
        $dim = (int) ($this->config['dim'] ?? 1024);

        $vector = [];

        try {
            if ($provider === 'ollama') {
                $base = config('llm.providers.ollama.base_url', 'http://localhost:11434');
                $resp = Http::timeout(30)->post(rtrim($base, '/').'/api/embeddings', [
                    'model' => $model,
                    'prompt' => $text,
                ])->throw()->json();
                $vector = $resp['embedding'] ?? [];
            } elseif ($provider === 'openai') {
                $base = config('llm.providers.openai.base_url', 'https://api.openai.com/v1');
                $key  = config('llm.providers.openai.api_key');
                $resp = Http::withToken($key)
                    ->timeout(30)
                    ->post(rtrim($base, '/').'/embeddings', [
                        'model' => $model,
                        'input' => $text,
                    ])->throw()->json();
                $vector = $resp['data'][0]['embedding'] ?? [];
            } else {
                // Default: try Ollama
                $base = config('llm.providers.ollama.base_url', 'http://localhost:11434');
                $resp = Http::timeout(30)->post(rtrim($base, '/').'/api/embeddings', [
                    'model' => $model,
                    'prompt' => $text,
                ])->throw()->json();
                $vector = $resp['embedding'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::error('Embedding request failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            $vector = [];
        }

        // Enforce dimension
        if (count($vector) !== $dim) {
            Log::warning('Embedding dimension mismatch', [
                'expected' => $dim,
                'got' => count($vector),
            ]);
            // Pad or trim to target dim
            if (count($vector) < $dim) {
                $vector = array_pad($vector, $dim, 0.0);
            } else {
                $vector = array_slice($vector, 0, $dim);
            }
        }

        return array_map(fn($v) => (float) $v, $vector);
    }

    public function storeEmailBodyEmbedding(string $emailMessageId, array $vec): void
    {
        $this->writeVector('email_messages', 'id', $emailMessageId, 'body_embedding', $vec);
    }

    public function storeAttachmentEmbedding(string $attachmentExtractionId, array $vec): void
    {
        $this->writeVector('attachment_extractions', 'id', $attachmentExtractionId, 'text_embedding', $vec);
    }

    public function storeMemoryEmbedding(string $memoryId, array $vec): void
    {
        $this->writeVector('memories', 'id', $memoryId, 'content_embedding', $vec);
    }

    /**
     * Persist vector using pgvector textual format. Uses parameter casting to vector.
     */
    private function writeVector(string $table, string $pkColumn, string $pk, string $column, array $vec): void
    {
        $literal = '['.implode(',', array_map(fn($v) => is_finite($v) ? (string) $v : '0', $vec)).']';
        DB::statement("UPDATE {$table} SET {$column} = ?::vector WHERE {$pkColumn} = ?", [$literal, $pk]);
    }

    /**
     * Assert vector matches configured dimension; throws InvalidArgumentException otherwise.
     */
    public function assertCorrectDimension(array $vector): void
    {
        $dim = (int) ($this->config['dim'] ?? config('llm.embeddings.dim', 1024));
        if (count($vector) !== $dim) {
            throw new \InvalidArgumentException("Embedding dimension mismatch. Expected {$dim}, got ".count($vector));
        }
    }
}


