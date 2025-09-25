<?php

/**
 * What this file does — Finds relevant snippets in your data using embeddings.
 * Plain: Turns the question into numbers and searches nearby pieces of text.
 * How this fits in:
 * - Called before answering to fetch context from emails/attachments/memories
 * - Helps the model quote real facts instead of guessing
 * - Feeds hitRate/topSim to the router
 * Key terms:
 * - embedding: number list representing meaning
 * - cosine distance: measure of closeness between embeddings
 *
 * For engineers:
 * - Inputs: query string, k
 * - Output: array of {src,id,text,similarity}
 * - Failure modes: empty results → hitRate 0; assumes pgvector enabled
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Purpose: Provide retrieval over pgvector columns in Postgres.
 * Responsibilities:
 * - Embed queries via Embeddings service
 * - Query three sources with IVFFlat indexes
 * - Compute simple metrics (hitRate, topSimilarity)
 * Collaborators: Embeddings, pgvector-enabled tables
 */
class GroundingService
{
    public function __construct(private Embeddings $embeddings) {}

    /**
     * Summary: Retrieve top-k snippets across email bodies, attachments, and memories.
     *
     * @param  string  $query  Natural language question
     * @param  int  $k  Number of items per source to return
     * @return array[] Each has src,id,text,similarity in [0,1]
     */
    public function retrieveTopK(string $query, int $k = 8): array
    {
        $vec = $this->embeddings->embedText($query);
        $literal = '['.implode(',', $vec).']';

        $sql = <<<'SQL'
(SELECT id, 'email_messages' AS src, body_text AS text, 1 - (body_embedding <=> ?::vector) AS sim
   FROM email_messages
  WHERE body_embedding IS NOT NULL
  ORDER BY body_embedding <=> ?::vector
  LIMIT ?)
UNION ALL
(SELECT id, 'attachment_extractions' AS src, text_excerpt AS text, 1 - (text_embedding <=> ?::vector) AS sim
   FROM attachment_extractions
  WHERE text_embedding IS NOT NULL
  ORDER BY text_embedding <=> ?::vector
  LIMIT ?)
UNION ALL
(SELECT id, 'memories' AS src, value_json::text AS text, 1 - (content_embedding <=> ?::vector) AS sim
   FROM memories
  WHERE content_embedding IS NOT NULL
  ORDER BY content_embedding <=> ?::vector
  LIMIT ?)
SQL;

        $params = [$literal, $literal, $k, $literal, $literal, $k, $literal, $literal, $k];
        $rows = DB::select($sql, $params);

        return array_map(fn ($r) => [
            'src' => $r->src,
            'id' => $r->id,
            'text' => $r->text,
            'similarity' => (float) $r->sim,
        ], $rows);
    }

    /**
     * Summary: Fraction of results above a minimum similarity bar (config/llm.routing.thresholds.grounding_hit_min analogue).
     *
     * @param  array  $results  Retrieval results from retrieveTopK
     * @return float 0..1
     */
    public function hitRate(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }
        $hits = 0;
        foreach ($results as $r) {
            if (($r['similarity'] ?? 0) >= 0.35) {
                $hits++;
            }
        }

        return $hits / max(1, count($results));
    }

    /**
     * Summary: Highest similarity score among results.
     */
    public function topSimilarity(array $results): float
    {
        $max = 0.0;
        foreach ($results as $r) {
            $max = max($max, (float) ($r['similarity'] ?? 0));
        }

        return $max;
    }
}
