<?php

declare(strict_types=1);

namespace App\Services\LocalRag;

use App\Exceptions\LocalRagException;
use App\Models\Document;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;

/**
 * Runs the local Python ingestion pipeline as an external CLI process.
 *
 * Security posture:
 *  - a dedicated venv interpreter resolved from config (`rag.python.binary`);
 *  - arguments are explicit and shell-escaped, never user-supplied paths;
 *  - the child process sees a minimal, explicit environment;
 *  - stdout/stderr follow a strict JSON contract (see `python/rag/contract.py`);
 *  - timeouts guarantee the parent request never hangs when Ollama or the
 *    database are unavailable.
 */
final class LocalRagService
{
    public function ingest(Document $document): IngestionResult
    {
        $document->update(['status' => 'processing']);

        $result = Process::timeout((float) Config::integer('rag.python.timeout'))
            ->path(base_path())
            ->env($this->processEnvironment())
            ->run(
                command: $this->command($document),
                output: fn (?string $type, string $output): string => $output,
            );

        $this->assertProcessSuccessful($result, $document);

        try {
            return IngestionResult::fromJson($this->decodeContract($result->output()));
        } catch (LocalRagException $exception) {
            $document->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);

            throw $exception;
        }
    }

    /**
     * Full CLI invocation, binary and script resolved from config.
     */
    public function command(Document $document): string
    {
        $binary = Config::string('rag.python.binary');
        $script = Config::string('rag.python.script_path');
        $documentId = escapeshellarg((string) $document->getKey());
        $filePath = escapeshellarg($this->absolutePath($document));
        $mimeType = escapeshellarg($document->mime_type);

        return escapeshellcmd("{$binary} {$script}").
            " --document-id {$documentId} --path {$filePath} --mime {$mimeType}";
    }

    /**
     * Minimal, explicit environment for the child process.
     *
     * @return array<string, string>
     */
    public function processEnvironment(): array
    {
        $connection = $this->databaseConnection();

        return [
            'OLLAMA_BASE_URL' => Config::string('rag.ollama.base_url'),
            'OLLAMA_EMBED_MODEL' => Config::string('rag.embeddings.model'),
            'RAG_EMBEDDING_DIMENSIONS' => Config::integer('rag.embeddings.dimensions'),
            'RAG_CHUNK_SIZE' => Config::integer('rag.chunking.size'),
            'RAG_DB_HOST' => $connection['host'] ?? '127.0.0.1',
            'RAG_DB_PORT' => $connection['port'] ?? '5432',
            'RAG_DB_USERNAME' => $connection['username'] ?? '',
            'RAG_DB_PASSWORD' => $connection['password'] ?? '',
            'RAG_DB_DATABASE' => $connection['database'] ?? '',
        ];
    }

    /**
     * Connection credentials of the RAG database, typed defensively.
     *
     * `rag.database_connection` lets the RAG stack point at a dedicated
     * PostgreSQL connection while the default connection serves the app.
     *
     * @return array{host?: string, port?: string|int, username?: string, password?: string, database?: string}
     */
    public function databaseConnection(): array
    {
        $configured = Config::get('rag.database_connection');

        $name = is_string($configured) && $configured !== ''
            ? $configured
            : Config::string('database.default');

        $connection = Config::get("database.connections.{$name}");

        return is_array($connection) ? $connection : [];
    }

    /**
     * Absolute location of the uploaded document inside the storage disk.
     */
    public function absolutePath(Document $document): string
    {
        return storage_path("app/{$document->file_path}");
    }

    private function assertProcessSuccessful(ProcessResult $result, Document $document): void
    {
        if ($result->successful()) {
            return;
        }

        $reason = $this->decodeFailureReason($result->errorOutput());

        $document->update(['status' => 'failed', 'failure_reason' => $reason]);

        throw new LocalRagException(
            sprintf(
                'Python ingestion failed for document #%d (exit %d): %s',
                $document->getKey(),
                $result->exitCode(),
                $reason,
            ),
        );
    }

    private function decodeFailureReason(string $errorOutput): string
    {
        $decoded = json_decode($errorOutput, true);

        if (is_array($decoded) && is_string($decoded['message'] ?? null)) {
            return (string) $decoded['message'];
        }

        return trim($errorOutput) !== '' ? trim($errorOutput) : 'Unknown python process failure (empty stderr).';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContract(string $output): array
    {
        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new LocalRagException(
                'Malformed ingestion contract: stdout was not valid JSON.',
            );
        }

        return $decoded;
    }
}
