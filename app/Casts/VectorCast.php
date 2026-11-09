<?php

declare(strict_types=1);

namespace App\Casts;

use App\Exceptions\VectorFormatException;
use App\Values\Vector;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use TypeError;

/**
 * Casts between pgvector's text format ("[1.0,2.0,...]") and {@see Vector}.
 *
 * When reading, values are validated against the configured embedding
 * dimensionality so corrupted rows surface immediately with a clear
 * exception instead of silently feeding wrong-length vectors into the
 * similarity search.
 */
final class VectorCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Vector
    {
        if ($value === null) {
            return null;
        }

        try {
            return Vector::fromString((string) $value);
        } catch (VectorFormatException $exception) {
            throw new VectorFormatException(
                "Invalid vector value for [{$model->getTable()}.{$key}]: {$exception->getMessage()}",
            );
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Vector) {
            return $value->toPsqlString();
        }

        if (is_array($value)) {
            return Vector::fromArray($value)->toPsqlString();
        }

        if (is_string($value)) {
            try {
                return Vector::fromString($value)->toPsqlString();
            } catch (VectorFormatException) {
                throw new VectorFormatException("Invalid vector representation for [{$key}].");
            }
        }

        throw new TypeError('Vector cast accepts Vector, array or pgvector string only.');
    }
}
