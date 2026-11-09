<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $file_path
 * @property string $mime_type
 * @property DocumentStatus $status
 * @property array<string, mixed>|null $metadata
 * @property string $content_hash
 * @property string|null $failure_reason
 * @property Carbon|null $ingested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $chunks_count
 *
 * @method static Builder<static> query()
 * @method static Builder<static> ready()
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'file_path',
        'mime_type',
        'status',
        'metadata',
        'content_hash',
        'failure_reason',
        'ingested_at',
    ];

    protected $withCount = ['chunks'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'metadata' => 'array',
            'ingested_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /** Documents ready for semantic retrieval. */
    #[Scope]
    protected function ready(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Ready);
    }
}
