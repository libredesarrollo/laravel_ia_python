<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enable the pgvector extension, required for `vector` column types,
     * the cosine distance operator (`<=>`) and HNSW indexes.
     *
     * Requires the pgvector shared library to be installed at the server
     * level (e.g. `apt install postgresql-XX-pgvector` or built from source)
     * before running `php artisan migrate` on a machine.
     */
    public function up(): void
    {
        // Only PostgreSQL implements the pgvector extension; SQLite in the
        // test suite runs without it.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    /**
     * Disabling the extension would drop every `vector` column silently.
     * This rollback is intentionally a no-op so it never destroys data.
     */
    public function down(): void
    {
        //
    }
};
