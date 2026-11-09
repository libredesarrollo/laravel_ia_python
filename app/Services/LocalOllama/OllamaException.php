<?php

declare(strict_types=1);

namespace App\Services\LocalOllama;

use RuntimeException;

/**
 * Failures while talking to the local Ollama server.
 */
final class OllamaException extends RuntimeException
{
    public static function unreachable(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Local Ollama server unreachable for {$path}. Is Ollama running on the configured host?",
            502,
            $previous,
        );
    }

    public static function requestFailed(string $path, int $status, string $body): self
    {
        return new self(
            sprintf('Ollama request failed (%s, HTTP %d): %s', $path, $status, mb_substr($body, 0, 300)),
            502,
        );
    }

    public static function malformedResponse(string $path): self
    {
        return new self(
            "Ollama returned a malformed response for {$path}.",
            502,
        );
    }

    public static function dimensionMismatch(int $returned, int $expected): self
    {
        return new self(
            sprintf(
                'Embedding dimension mismatch: Ollama returned %d components, database expects %d. '.
                'Review RAG_EMBEDDING_DIMENSIONS and the configured embedding model.',
                $returned,
                $expected,
            ),
            502,
        );
    }
}
