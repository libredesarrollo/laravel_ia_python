<?php

declare(strict_types=1);

namespace App\Services\LocalRag;

use App\Exceptions\LocalRagException;

/**
 * Structured result of a successful Python ingestion run (contract JSON).
 */
final readonly class IngestionResult
{
    public function __construct(
        public int $documentId,
        public int $chunks,
        public int $embeddingBatches,
        public int $durationMs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromJson(array $payload): self
    {
        $documentId = self::expectInteger($payload, 'document_id');
        $chunks = self::expectInteger($payload, 'chunks');
        $batchCount = self::expectInteger($payload, 'embeddings_batches');
        $durationMs = self::expectInteger($payload, 'duration_ms');

        return new self(
            documentId: $documentId,
            chunks: $chunks,
            embeddingBatches: $batchCount,
            durationMs: $durationMs,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function expectInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw new LocalRagException(
                "Malformed ingestion contract: expected integer key [{$key}].",
            );
        }

        return $value;
    }
}
