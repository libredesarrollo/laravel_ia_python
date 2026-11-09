<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Services\LocalRag\LocalRagService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Document library of the local knowledge base: upload, ingest, inspect, delete.
 */
final class DocumentController extends Controller
{
    public function __construct(
        private readonly LocalRagService $rag,
    ) {}

    public function index(): View
    {
        $documents = Document::query()
            ->withCount('chunks')
            ->latest()
            ->get();

        return view('admin.documents.index', ['documents' => $documents]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('document');

        $storedPath = $file->store(config('rag.uploads.directory'));
        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $contentHash = hash_file('sha256', $file->getPathname());

        Document::query()->create([
            'title' => $originalName,
            'file_path' => $storedPath,
            'mime_type' => $mimeType ?? '',
            'status' => DocumentStatus::Pending,
            'metadata' => [
                'original_name' => $originalName,
                'size_bytes' => $fileSize,
                'uploaded_by_ip' => $request->ip(),
            ],
            'content_hash' => $contentHash === false ? md5($storedPath) : $contentHash,
        ]);

        return redirect()
            ->route('admin.documents.index')
            ->with('status', 'Documento registrado. Ejecuta la ingesta para vectorizarlo.');
    }

    /**
     * Launch (or relaunch) the Python ingestion for one document.
     */
    public function ingest(Document $document, LocalRagService $service): RedirectResponse
    {
        try {
            $result = $service->ingest($document);

            return redirect()
                ->route('admin.documents.index')
                ->with('status', sprintf(
                    'Ingesta completada: %d fragmentos vectorizados en %d ms.',
                    $result->chunks,
                    $result->durationMs,
                ));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.documents.index')
                ->withErrors(['ingest' => 'La ingesta falló: '.$exception->getMessage()]);
        }
    }

    public function destroy(Document $document): RedirectResponse
    {
        // Chunks are removed by cascading FK; only the physical file remains.
        if ($document->file_path !== '') {
            try {
                Storage::disk(config('rag.uploads.disk'))->delete($document->file_path);
            } catch (RuntimeException $exception) {
                report($exception);
            }
        }

        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('status', 'Documento eliminado de la base de conocimiento.');
    }
}
