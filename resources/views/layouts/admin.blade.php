<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="panel-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · IA Local RAG</title>

    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="min-h-screen bg-slate-900 font-sans text-slate-200 antialiased">

<div class="flex min-h-screen">

    {{-- Sidebar corporativa --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-950/60 px-4 py-6 md:flex">
        <div class="mb-8 flex items-center gap-3 px-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-cyan-600 font-bold text-white">R</div>
            <div>
                <p class="text-sm font-semibold text-slate-100">IA Local RAG</p>
                <p class="text-xs text-slate-500">100% on-premise</p>
            </div>
        </div>

        <nav class="flex flex-col gap-1 text-sm">
            <a href="{{ route('admin.chat.index') }}"
               class="rounded-lg px-3 py-2 font-medium transition
                      {{ request()->routeIs('admin.chat.*') ? 'bg-cyan-950/60 text-cyan-300' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100' }}">
                💬 Chat corporativo
            </a>
            <a href="{{ route('admin.documents.index') }}"
               class="rounded-lg px-3 py-2 font-medium transition
                      {{ request()->routeIs('admin.documents.*') ? 'bg-cyan-950/60 text-cyan-300' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100' }}">
                📚 Base documental
            </a>
        </nav>

        <div class="mt-auto rounded-lg border border-slate-800 bg-slate-900/60 p-3 text-xs text-slate-500">
            <p class="font-medium text-slate-300">Privacidad total</p>
            <p class="mt-1 leading-relaxed">Ollama · embedding {{ config('rag.embeddings.model') }} · pgvector({{ config('rag.embeddings.dimensions') }})</p>
        </div>
    </aside>

    {{-- Contenido principal --}}
    <main class="flex min-h-screen flex-1 flex-col">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4 md:hidden">
            <span class="font-semibold text-slate-100">IA Local RAG</span>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.chat.index') }}" class="text-slate-400 hover:text-cyan-300">Chat</a>
                <a href="{{ route('admin.documents.index') }}" class="text-slate-400 hover:text-cyan-300">Documentos</a>
            </div>
        </header>

        <div class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 md:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-900/70 bg-rose-950/40 px-4 py-3 text-sm text-rose-300">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-900/70 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

</body>
</html>
