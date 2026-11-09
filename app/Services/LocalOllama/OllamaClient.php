<?php

declare(strict_types=1);

namespace App\Services\LocalOllama;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Client for the local Ollama server (localhost only).
 *
 * Both token APIs (/api/embed and /api/chat) stay inside the corporate
 * network; responses are never forwarded to third-party services.
 */
final class OllamaClient
{
    /**
     * Embed a single text with the configured embedding model.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $response = $this->http()->post(
            '/api/embed',
            [
                'model' => Config::string('rag.embeddings.model'),
                'input' => $text,
            ],
        );

        throw_if(
            $response->failed(),
            OllamaException::requestFailed('embedding', $response->status(), $response->body()),
        );

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings) || $embeddings === [] || ! is_array($embeddings[0])) {
            throw OllamaException::malformedResponse('/api/embed');
        }

        $expectedDimensions = Config::integer('rag.embeddings.dimensions');
        $components = array_map(floatval(...), $embeddings[0]);

        if ($expectedDimensions !== 0 && count($components) !== $expectedDimensions) {
            throw OllamaException::dimensionMismatch(
                count($components),
                $expectedDimensions,
            );
        }

        return $components;
    }

    /**
     * Stream a chat completion through the local Ollama /api/chat endpoint.
     *
     * Ollama responds with newline-delimited JSON objects, each carrying the
     * next message fragment under `message.content` until `done` is `true`.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onToken  Receives raw message fragments.
     * @param  string  $model  - overrides the configured chat model.
     */
    public function streamChat(
        array $messages,
        callable $onToken,
        string $model = '',
    ): void {
        $body = $this->responseBody(
            '/api/chat',
            [
                'model' => $model === '' ? Config::string('rag.ollama.chat_model') : $model,
                'messages' => $messages,
                'stream' => true,
                'keep_alive' => Config::string('rag.ollama.keep_alive'),
            ],
        );

        foreach (explode("\n", $body) as $line) {
            if ($line === '') {
                continue;
            }

            $payload = $this->decodeLine($line);
            $content = $payload['message']['content'] ?? '';

            if (is_string($content) && $content !== '') {
                $onToken($content);
            }
        }
    }

    /**
     * Stream a chat completion fully into a string (used by tests / sync tools).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function completeChat(array $messages, string $model = ''): string
    {
        $content = '';

        $this->streamChat(
            $messages,
            function (string $token) use (&$content): void {
                $content .= $token;
            },
            $model,
        );

        return $content;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function responseBody(string $path, array $body): string
    {
        try {
            $response = $this->http()->withOptions(['stream' => true])->post($path, $body);
        } catch (ConnectionException $exception) {
            throw OllamaException::unreachable($path, $exception);
        }

        if ($response->failed()) {
            throw OllamaException::requestFailed($path, $response->status(), $response->body());
        }

        return $response->body();
    }

    /**
     * @return array{message?: array{content?: mixed}, done?: bool}
     */
    private function decodeLine(string $line): array
    {
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function http(): PendingRequest
    {
        $baseUrl = Config::string('rag.ollama.base_url');
        $timeout = (int) Config::integer('rag.ollama.connect_timeout', 120);

        return Http::baseUrl($baseUrl)->timeout($timeout);
    }
}
