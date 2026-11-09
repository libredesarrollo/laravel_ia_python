<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\LocalRagException;
use App\Models\Document;
use App\Services\LocalRag\LocalRagService;
use Illuminate\Console\Command;
use Throwable;

class RagIngestCommand extends Command
{
    protected $signature = 'rag:ingest {document : Document ID or "all" for ready-pending documents}';

    protected $description = 'Run the local Python RAG ingestion pipeline for one or all pending documents';

    public function handle(LocalRagService $service): int
    {
        $target = $this->argument('document');

        $documents = $target === 'all'
            ? Document::query()->whereIn('status', ['pending', 'failed'])->get()
            : Document::query()->whereKey((int) $target)->get();

        if ($documents->isEmpty()) {
            $this->warn('No matching documents found.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($documents as $document) {
            $this->ingestDocument($document, $service, $failures);
        }

        if ($failures > 0) {
            $this->warn("{$failures} document(s) failed.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ingestDocument(
        Document $document,
        LocalRagService $service,
        int &$failures,
    ): void {
        $this->info("Ingesting #{$document->getKey()} [{$document->title}] ...");

        try {
            $result = $service->ingest($document);

            $this->table(
                ['document_id', 'chunks', 'embedding_batches', 'duration_ms'],
                [[$result->documentId, $result->chunks, $result->embeddingBatches, $result->durationMs]],
            );
        } catch (LocalRagException $exception) {
            $failures++;

            $this->error("{$exception->getCode()} - {$exception->getMessage()}");
        } catch (Throwable $exception) {
            $failures++;

            $this->error($exception->getMessage());
        }
    }
}
