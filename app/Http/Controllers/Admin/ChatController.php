<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Chat\ChatService;
use App\Services\LocalOllama\OllamaClient;
use App\Services\Retrieval\RetrievalHit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Corporate chat: question in, streamed RAG answer out.
 *
 * The stream is a Server-Sent-Events (SSE) channel driven by Laravel's
 * StreamedResponse, forwarding the token stream of the local Ollama API to
 * the browser. No bytes ever leave the server except through this response.
 */
final class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly OllamaClient $ollama,
    ) {}

    public function index(): View
    {
        return view('admin.chat.index', [
            'documents' => Document::query()->ready()->select(['id', 'title'])->latest()->get(),
        ]);
    }

    /**
     * Validate the question and emit the SSE stream.
     */
    public function stream(Request $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:2000'],
            'document_id' => ['nullable', 'string'],
        ]);

        $question = trim((string) $request->input('question'));
        $documentId = $request->filled('document_id') ? (int) $request->input('document_id') : null;

        $hits = $this->chat->context($question, $documentId);
        $messages = $this->chat->messages($question, $hits);

        if ($hits === []) {
            return response()->json([
                'message' => 'No hay fragmentos relevantes en la base de conocimiento. Sube y vectoriza documentos primero.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->stream(function () use ($hits, $messages): void {
            $this->emit('sources', $this->citations($hits));

            $this->chat->streamAnswer($messages, function (string $token): void {
                $this->emit('token', ['t' => $token]);
            });

            $this->emit('done', ['ok' => true]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Availability probe for the chat view indicator.
     */
    public function status(): JsonResponse
    {
        try {
            $this->ollama->embed('ping');

            return response()->json(['ollama' => 'up']);
        } catch (Throwable) {
            return response()->json(['ollama' => 'down'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * @param  iterable<RetrievalHit>  $hits
     * @return list<array{document: string, chunk: int, similarity: float}>
     */
    private function citations(iterable $hits): array
    {
        $citations = [];

        foreach ($hits as $hit) {
            $citations[] = [
                'document' => $hit->document->title,
                'chunk' => $hit->chunkIndex,
                'similarity' => round($hit->similarity, 4),
            ];
        }

        return $citations;
    }

    /**
     * Send one properly framed SSE event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function emit(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
