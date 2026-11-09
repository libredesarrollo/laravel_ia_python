<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Models\Document;
use App\Values\Vector;

/**
 * A single retrieved chunk, enriched with its source document and similarity.
 */
final readonly class RetrievalHit
{
    public function __construct(
        public int $chunkId,
        public int $chunkIndex,
        public string $content,
        public Vector $embedding,
        public Document $document,
        /** Cosine similarity in [0, 1] (1 = identical direction). */
        public float $similarity,
    ) {}
}
