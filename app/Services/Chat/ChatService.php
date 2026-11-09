<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\LocalOllama\OllamaClient;
use App\Services\Retrieval\RetrievalHit;
use App\Services\Retrieval\RetrievalService;

/**
 * Retrieval-Augmented Generation end to end, 100% local.
 *
 * 1. retrieve relevant chunks for the user's question (pgvector);
 * 2. build an augmented prompt quoting those chunks with sources;
 * 3. delegate streaming generation to the local Ollama client.
 */
final class ChatService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Eres un asistente corporativo que responde EXCLUSIVAMENTE en base al contexto
        documental interno proporcionado. Esas fuentes son la única información
        permitida. Si el contexto no contiene la respuesta, indícalo con franqueza
        y sugiere amplificar la base documental. No inventes datos, no uses
        conocimiento externo ni hables de procesos internos técnicos.
        PROMPT;

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly RetrievalService $retrieval,
    ) {}

    /**
     * @return list<RetrievalHit>
     */
    public function context(string $question, ?int $documentId = null): array
    {
        return $this->retrieval->retrieve($question, documentId: $documentId);
    }

    /**
     * Build the augmented message list for the chat API.
     *
     * @param  list<RetrievalHit>  $hits
     * @return list<array{role: string, content: string}>
     */
    public function messages(string $question, array $hits): array
    {
        if ($hits === []) {
            return [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $question],
            ];
        }

        return [
            [
                'role' => 'system',
                'content' => self::SYSTEM_PROMPT."\n\n".self::contextBlock($hits),
            ],
            ['role' => 'user', 'content' => $question],
        ];
    }

    /**
     * Stream the answer token by token through `$onToken`.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function streamAnswer(array $messages, callable $onToken): void
    {
        $this->ollama->streamChat($messages, $onToken);
    }

    /**
     * Convenience: context + messages + streaming in one call.
     */
    public function answer(string $question, callable $onToken, ?int $documentId = null): void
    {
        $hits = $this->context($question, $documentId);
        $this->streamAnswer($this->messages($question, $hits), $onToken);
    }

    /**
     * @param  list<RetrievalHit>  $hits
     */
    private static function contextBlock(array $hits): string
    {
        $blocks = '';

        foreach ($hits as $position => $hit) {
            $source = sprintf(
                '[Fuente %d] Documento: "%s" (fragmento %d, similitud %.2f)',
                $position + 1,
                $hit->document->title,
                $hit->chunkIndex,
                $hit->similarity,
            );

            $blocks .= "{$source}\n{$hit->content}\n\n";
        }

        return "CONTEXTO DOCUMENTAL INTERNO:\n\n".trim($blocks).
            "\n\nUsa los fragmentos anteriores como única fuente de verdad y cita las [Fuente N] en tu respuesta.";
    }
}
