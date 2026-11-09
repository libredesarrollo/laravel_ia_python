@extends('layouts.admin')

@section('title', 'Chat')

@section('content')
<div class="flex h-[calc(100vh-4rem)] flex-col">
    <div id="chat" class="flex flex-1 flex-col gap-4 overflow-hidden">

        {{-- Cabecera --}}
        <div class="panel-card flex items-center justify-between px-5 py-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-100">Chat corporativo</h1>
                <p class="text-xs text-slate-500">Recuperación semántica + generación local ({{ config('rag.ollama.chat_model') }})</p>
            </div>

            <span id="ragStatus" class="panel-badge border border-slate-700 bg-slate-800/60 text-slate-400"
                  data-url="{{ route('admin.chat.status') }}">● comprobando...</span>
        </div>

        {{-- Mensajes --}}
        <div id="messages" class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/50 p-4">
            <div class="mx-auto max-w-2xl rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-6 text-center text-sm text-slate-500">
                Formula una pregunta sobre la base documental. La respuesta se genera en tu equipo con Ollama y pgvector.
            </div>
        </div>

        {{-- Citas de la última respuesta --}}
        <div id="citations" class="hidden panel-card px-4 py-3 text-xs text-slate-400">
            <p class="mb-1 font-semibold text-slate-300">Fuentes recuperadas:</p>
            <ul id="citationsList" class="list-inside list-disc space-y-0.5"></ul>
        </div>

        {{-- Formulario --}}
        <form id="chatForm" action="{{ route('admin.chat.stream') }}" method="POST" class="panel-card flex gap-2 p-3">
            @csrf
            <input type="text" name="question" id="question" autocomplete="off" required
                   placeholder="¿Qué quieres consultar sobre los documentos internos?"
                   class="panel-input flex-1 bg-transparent">
            <select name="document_id" id="document_id" class="panel-input w-48 bg-transparent text-xs">
                <option value="">Todo el corpus</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->id }}">{{ \Illuminate\Support\Str::limit($document->title, 28) }}</option>
                @endforeach
            </select>
            <button type="submit" id="sendButton" class="panel-btn-primary w-28">
                Enviar
            </button>
        </form>
    </div>
</div>
@endsection
