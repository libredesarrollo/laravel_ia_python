<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'title' => $name,
            'file_path' => 'documents/'.$name,
            'mime_type' => 'application/pdf',
            'status' => fake()->randomElement(DocumentStatus::values()),
            'metadata' => ['original_name' => $name],
            'content_hash' => fake()->sha256(),
            'ingested_at' => null,
        ];
    }

    /** Document ready for retrieval. */
    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Ready,
            'ingested_at' => now(),
        ]);
    }

    /** Document pending ingestion. */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Pending,
        ]);
    }

    /** Document whose ingestion failed. */
    public function failedWith(string $reason = 'Embedding service unavailable.'): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
