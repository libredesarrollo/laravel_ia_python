<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Exceptions\RetrievalException;
use App\Models\DocumentChunk;
use App\Services\LocalOllama\OllamaClient;
use App\Values\Vector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Semantic retrieval over document chunks: cosine similarity via pgvector.
 *
 * The pipeline: question -> local embedding -> `<=>` ordered HNSW search ->
 * typed hits enriched with their source document for citation.
 */
final class RetrievalService
{
    public function __construct(
        private readonly OllamaClient $ollama,
    ) {}

    /**
     * Retrieve the most relevant chunks for the user's question.
     *
     * @return list<RetrievalHit>
     */
    public function retrieve(
        string $question,
        int $topK = 0,
        float $minSimilarity = -1.0,
        ?int $documentId = null,
    ): array {
        $question = trim($question);

        if ($question === '') {
            throw RetrievalException::emptyQuery();
        }

        $components = $this->ollama->embed($question);
        $embedding = Vector::fromArray($components);

        $query = DocumentChunk::query()
            ->nearestNeighbors(
                embedding: $embedding,
                topK: $topK > 0 ? $topK : Config::integer('rag.retrieval.top_k'),
                minSimilarity: $minSimilarity >= 0.0 ? $minSimilarity : Config::float('rag.retrieval.min_similarity'),
            )
            ->with('document');

        if ($documentId !== null) {
            $query->where('document_id', $documentId);
        }

        /** @var Collection<int, DocumentChunk> $chunks */
        $chunks = $query->get();

        return $chunks
            ->filter(fn (DocumentChunk $chunk) => $chunk->document !== null)
            ->map(fn (DocumentChunk $chunk) => new RetrievalHit(
                chunkId: $chunk->getKey(),
                chunkIndex: $chunk->chunk_index,
                content: $chunk->content,
                embedding: $chunk->embedding ?? $embedding,
                document: $chunk->document,
                similarity: $chunk->distance === null
                    ? 0.0
                    : max(0.0, 1.0 - $chunk->distance),
            ))
            ->values()
            ->all();
    }
}
