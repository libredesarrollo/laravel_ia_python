<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents ingested into the local RAG knowledge base.
     *
     * Physical files are stored outside the database; this table keeps the
     * catalog plus the ingestion lifecycle state for the admin panel.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type', 127)->index();
            $table->string('status', 31)->default('pending')->index();
            $table->jsonb('metadata')->nullable();
            $table->string('content_hash', 64)->unique();
            $table->text('failure_reason')->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
