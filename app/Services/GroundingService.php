<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GroundingService
{
    public function __construct(private Embeddings $embeddings)
    {
    }

    /**
     * Retrieve top-k snippets across email bodies, attachments, and memories.
     * Returns array of [src, id, text, sim].
     */
    public function retrieveTopK(string $query, int $k = 8): array
    {
        $vec = $this->embeddings->embedText($query);
        $literal = '['.implode(',', $vec).']';

        $sql = <<<SQL
SELECT id, 'email_messages' AS src, body_text AS text, 1 - (body_embedding <=> ?::vector) AS sim
  FROM email_messages
 WHERE body_embedding IS NOT NULL
 ORDER BY body_embedding <=> ?::vector
 LIMIT ?
UNION ALL
SELECT id, 'attachment_extractions' AS src, text_excerpt AS text, 1 - (text_embedding <=> ?::vector) AS sim
  FROM attachment_extractions
 WHERE text_embedding IS NOT NULL
 ORDER BY text_embedding <=> ?::vector
 LIMIT ?
UNION ALL
SELECT id, 'memories' AS src, value_json::text AS text, 1 - (content_embedding <=> ?::vector) AS sim
  FROM memories
 WHERE content_embedding IS NOT NULL
 ORDER BY content_embedding <=> ?::vector
 LIMIT ?
SQL;

        $params = [$literal, $literal, $k, $literal, $literal, $k, $literal, $literal, $k];
        $rows = DB::select($sql, $params);
        return array_map(fn($r) => [
            'src' => $r->src,
            'id' => $r->id,
            'text' => $r->text,
            'similarity' => (float) $r->sim,
        ], $rows);
    }

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

    public function topSimilarity(array $results): float
    {
        $max = 0.0;
        foreach ($results as $r) {
            $max = max($max, (float) ($r['similarity'] ?? 0));
        }
        return $max;
    }
}


