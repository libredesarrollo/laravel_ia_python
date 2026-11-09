<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vectorized fragments ("chunks") of a document.
     *
     * The embedding dimension must match the Ollama embedding model
     * (`nomic-embed-text` produces 768 dimensions by default).
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
        });

        // Only PostgreSQL serves `vector` columns + HNSW index; tests fall
        // back to SQLite which cannot parse the vector() type.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE document_chunks ADD COLUMN embedding vector(%d)',
                Config::integer('rag.embeddings.dimensions'),
            ));

            // HNSW index tuned for cosine similarity search (`<=>` operator).
            DB::statement(
                'CREATE INDEX IF NOT EXISTS document_chunks_embedding_hnsw_index '.
                'ON document_chunks USING hnsw (embedding vector_cosine_ops)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
