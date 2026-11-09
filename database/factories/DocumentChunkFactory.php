<?php

namespace Database\Factories;

use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chunk_index' => 0,
            'content' => fake()->paragraphs(asText: true),
            'metadata' => null,
            'embedding' => array_fill(0, config('rag.embeddings.dimensions'), 0.1),
            'token_count' => fake()->numberBetween(20, 400),
        ];
    }

    /** Sequence chunk indexes after related document chunks. */
    public function indexed(int $index): static
    {
        return $this->state(fn (): array => ['chunk_index' => $index]);
    }
}
