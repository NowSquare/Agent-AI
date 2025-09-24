<?php

namespace App\DTO;

/**
 * Immutable Vote DTO representing a single scoring event against a candidate.
 *
 * Fields:
 * - candidate_id: string ULID of the candidate (e.g., Task ID)
 * - score: float in [0,1]
 * - reasons: string[] compact rationale messages
 * - evidence_ids: string[] identifiers of provenance/evidence used
 */
final class Vote
{
    /** @param string[] $reasons @param string[] $evidenceIds */
    public function __construct(
        public readonly string $candidateId,
        public readonly float $score,
        public readonly array $reasons = [],
        public readonly array $evidenceIds = [],
    ) {}
}


