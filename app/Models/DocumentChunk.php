<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\VectorCast;
use App\Values\Vector;
use Database\Factories\DocumentChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int $chunk_index
 * @property string $content
 * @property array<string, mixed>|null $metadata
 * @property Vector|null $embedding
 * @property int $token_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property float|null $distance
 * @property-read Document $document
 *
 * @method static Builder<static> query()
 * @method static Builder<static> nearestNeighbors(Vector $embedding, int $topK = 5, float $minSimilarity = 0.0)
 */
class DocumentChunk extends Model
{
    /** @use HasFactory<DocumentChunkFactory> */
    use HasFactory;

    protected $table = 'document_chunks';

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'metadata',
        'embedding',
        'token_count',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'embedding' => VectorCast::class,
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Cosine similarity search against pgvector's `<=>` operator.
     *
     * The `distance` alias holds cosine distance (1 - similarity), so the
     * minimum-similarity filter is expressed as a distance ceiling.
     */
    #[Scope]
    protected function nearestNeighbors(
        Builder $query,
        Vector $embedding,
        int $topK = 5,
        float $minSimilarity = 0.0,
    ): Builder {
        return $query
            ->select('document_chunks.*')
            ->selectRaw('embedding <=> ? AS distance', [$embedding->toPsqlString()])
            ->whereRaw('embedding <=> ? <= ?', [$embedding->toPsqlString(), 1 - $minSimilarity])
            ->orderByRaw('distance, document_chunks.id')
            ->limit($topK);
    }
}
