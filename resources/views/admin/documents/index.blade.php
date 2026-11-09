@extends('layouts.admin')

@section('title', 'Base documental')

@section('content')
<div class="flex flex-col gap-6">

    {{-- Subida --}}
    <div class="panel-card p-5">
        <h1 class="text-lg font-semibold text-slate-100">Base documental</h1>
        <p class="mt-1 text-xs text-slate-500">
            Sube documentos internos (PDF, texto, Markdown, CSV). La ingesta trocea el contenido, genera
            embeddings con Ollama ({{ config('rag.embeddings.model') }}) y los almacena en
            pgvector({{ config('rag.embeddings.dimensions') }}). Archivos locales únicamente.
        </p>

        <form action="{{ route('admin.documents.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="document" required
                   accept="{{ collect(config('rag.uploads.allowed_mimes'))->map(fn (string $ext): string => '.'.$ext)->implode(',') }}"
                   class="panel-input flex-1 min-w-64 file:mr-3 file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-1 file:text-xs file:text-slate-300">
            <button type="submit" class="panel-btn-primary">Subir documento</button>
        </form>
    </div>

    {{-- Listado --}}
    <div class="panel-card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-800 bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3">Documento</th>
                    <th class="px-3 py-3">Estado</th>
                    <th class="px-3 py-3">Fragmentos</th>
                    <th class="px-3 py-3"> Error</th>
                    <th class="px-5 py-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-800/60">
                @forelse ($documents as $document)
                    <tr class="transition hover:bg-slate-800/30">
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-200">{{ $document->title }}</p>
                            <p class="text-xs text-slate-500">{{ $document->mime_type }} · {{ \Illuminate\Support\Str::of($document->file_path)->basename() }} · {{ $document->created_at->format('d M Y H:i') }}</p>
                        </td>

                        <td class="px-3 py-3">
                            @switch(true)
                                @case($document->status === \App\Enums\DocumentStatus::Ready)
                                    <span class="panel-badge bg-emerald-950/60 text-emerald-300 border border-emerald-900/60">● Vectorizado</span>
                                    @break
                                @case($document->status === \App\Enums\DocumentStatus::Processing)
                                    <span class="panel-badge bg-amber-950/60 text-amber-300 border border-amber-900/60">◌ Procesando…</span>
                                    @break
                                @case($document->status === \App\Enums\DocumentStatus::Failed)
                                    <span class="panel-badge bg-rose-950/60 text-rose-300 border border-rose-900/60">✕ Falló</span>
                                    @break
                                @default
                                    <span class="panel-badge bg-slate-800/80 text-slate-400 border border-slate-700">○ Pendiente</span>
                            @endswitch
                        </td>

                        <td class="px-3 py-3 text-slate-300">{{ $document->chunks_count }}</td>
                        <td class="px-3 py-3 text-xs text-rose-300">{{ $document->failure_reason != '' ? \Illuminate\Support\Str::limit($document->failure_reason, 80) : '' }}</td>

                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                <form action="{{ route('admin.documents.ingest', $document) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="panel-btn-ghost text-xs">Ingesta (Python)</button>
                                </form>

                                <form action="{{ route('admin.documents.destroy', $document) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Eliminar este documento y sus fragmentos?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="panel-btn-danger">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                            No hay documentos en la base de conocimiento todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
