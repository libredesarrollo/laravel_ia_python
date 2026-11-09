<?php

use App\Enums\DocumentStatus;
use App\Exceptions\LocalRagException;
use App\Models\Document;
use App\Services\LocalRag\IngestionResult;
use App\Services\LocalRag\LocalRagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

describe('process orchestration (Laravel Process -> python)', function () {
    beforeEach(function () {
        config([
            'rag.python.binary' => 'python',
            'rag.python.script_path' => 'python/process_documents.py',
            'rag.python.timeout' => 1200,
        ]);
    });

    it('builds a safe CLI invocation for the local python script', function () {
        $document = Document::query()->create([
            'title' => 'manual.pdf',
            'file_path' => 'documents/manual.pdf',
            'mime_type' => 'application/pdf',
            'status' => DocumentStatus::Pending,
            'metadata' => null,
            'content_hash' => 'abc123',
        ]);

        $service = app(LocalRagService::class);
        $command = $service->command($document);

        expect($command)
            ->toContain('python/process_documents.py')
            ->toContain("--document-id '".$document->id."'")
            ->toContain("--path '")
            ->toContain('storage/app/documents/manual.pdf')
            ->toContain("mime 'application/pdf'");
    });

    it('passes only the minimal RAG environment to the child process', function () {
        config([
            'rag.database_connection' => 'rag_pgsql',
            'database.connections.rag_pgsql' => [
                'host' => 'db.internal',
                'port' => '5432',
                'username' => 'rag_user',
                'password' => 'secret',
                'database' => 'rag_local',
            ],
        ]);

        $env = app(LocalRagService::class)->processEnvironment();

        expect($env)
            ->toMatchArray([
                'RAG_DB_HOST' => 'db.internal',
                'RAG_DB_PORT' => '5432',
                'RAG_DB_USERNAME' => 'rag_user',
                'RAG_DB_PASSWORD' => 'secret',
                'RAG_DB_DATABASE' => 'rag_local',
                'RAG_CHUNK_SIZE' => 1000,
            ])
            ->and(array_keys($env))
            ->toBe([
                'OLLAMA_BASE_URL',
                'OLLAMA_EMBED_MODEL',
                'RAG_EMBEDDING_DIMENSIONS',
                'RAG_CHUNK_SIZE',
                'RAG_DB_HOST',
                'RAG_DB_PORT',
                'RAG_DB_USERNAME',
                'RAG_DB_PASSWORD',
                'RAG_DB_DATABASE',
            ]);
    });

    it('runs the python process and decodes the success contract', function () {
        $document = Document::query()->create([
            'title' => 'doc.md',
            'file_path' => 'documents/doc.md',
            'mime_type' => 'text/markdown',
            'status' => DocumentStatus::Pending,
            'metadata' => null,
            'content_hash' => 'a-hash',
        ]);

        $stdout = json_encode([
            'status' => 'ok',
            'document_id' => $document->id,
            'chunks' => 12,
            'embeddings_batches' => 1,
            'duration_ms' => 2500,
        ]);

        Process::fake([
            '*' => Process::result(output: $stdout, errorOutput: '', exitCode: 0),
        ]);

        $result = app(LocalRagService::class)->ingest($document);

        expect($result)
            ->toBeInstanceOf(IngestionResult::class)
            ->chunks->toBe(12)
            ->durationMs->toBe(2500);
        // Document status transitions to `ready` in the child process via the
        // database contract; the PHP run exits while status is `processing`.
        expect($document->fresh()->status)->not->toBe(DocumentStatus::Failed);
    });

    it('marks the document failed and throws when the python process fails', function () {
        $document = Document::query()->create([
            'title' => 'broken.pdf',
            'file_path' => 'documents/broken.pdf',
            'mime_type' => 'application/pdf',
            'status' => DocumentStatus::Processing,
            'metadata' => null,
            'content_hash' => 'hash',
        ]);

        $stderr = json_encode([
            'status' => 'error',
            'code' => 'embed_error',
            'message' => 'Ollama /api/embed unreachable',
            'document_id' => $document->id,
        ]);

        Process::fake([
            '*' => Process::result(output: '', errorOutput: $stderr, exitCode: 1),
        ]);

        expect(fn () => app(LocalRagService::class)->ingest($document))
            ->toThrow(LocalRagException::class, 'Ollama /api/embed unreachable')
            ->and($document->fresh())
            ->status->toBe(DocumentStatus::Failed)
            ->failure_reason->toContain('Ollama /api/embed unreachable');
    });

    it('rejects malformed contract payloads (stdout is not JSON)', function () {
        $document = Document::query()->create([
            'title' => 'doc.txt',
            'file_path' => 'documents/doc.txt',
            'mime_type' => 'text/plain',
            'status' => DocumentStatus::Processing,
            'metadata' => null,
            'content_hash' => 'hash',
        ]);

        Process::fake([
            '*' => Process::result(output: 'Traceback (most recent call last)...', errorOutput: '', exitCode: 0),
        ]);

        expect(fn () => app(LocalRagService::class)->ingest($document))
            ->toThrow(LocalRagException::class, 'stdout was not valid JSON')
            ->and($document->fresh()->status)
            ->toBe(DocumentStatus::Failed);
    });
});
