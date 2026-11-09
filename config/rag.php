<?php

/**
 * Local RAG pipeline configuration.
 *
 * Everything in this stack runs on-premise: the Python processor is an
 * internal script, the database is the configured PostgreSQL connection and
 * Ollama is expected to serve models from localhost only. No third-party
 * service is ever contacted, which is the privacy guarantee of this product.
 */
return [

    /**
     * Dedicated PostgreSQL connection used by the RAG stack (python process
     * and vector search). Falls back to `database.default` when unset.
     */
    'database_connection' => env('RAG_DATABASE_CONNECTION'),

    // RAG python process pipeline configuration.
    'python' => [
        'binary' => env('RAG_PYTHON_BINARY', 'python'),
        'script_path' => env('RAG_SCRIPT_PATH', 'python/process_documents.py'),
        'timeout' => (int) env('RAG_PROCESS_TIMEOUT', 1200),
    ],

    // Local Ollama server (localhost only - never exposed).
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'gemma4:12b'),
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '10m'),
        'connect_timeout' => (int) env('OLLAMA_TIMEOUT', 180),
    ],

    // Document chunking strategy.
    'chunking' => [
        'size' => (int) env('RAG_CHUNK_SIZE', 1000),
        'overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),
    ],

    // Local embedding model details (must match what nomic-embed-text serves).
    'embeddings' => [
        'model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 768),
        'batch_size' => (int) env('OLLAMA_EMBED_BATCH_SIZE', 32),
    ],

    // Retrieval defaults.
    'retrieval' => [
        'top_k' => (int) env('RAG_TOP_K', 5),
        'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.0),
    ],

    // Allowed upload types for the admin panel.
    'uploads' => [
        'disk' => env('RAG_UPLOAD_DISK', 'local'),
        'directory' => 'documents',
        'max_size_kb' => (int) env('RAG_UPLOAD_MAX_KB', 20480),
        'allowed_mimes' => ['pdf', 'txt', 'md', 'csv'],
    ],
];
