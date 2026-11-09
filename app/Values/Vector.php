<?php

declare(strict_types=1);

namespace App\Values;

use App\Exceptions\VectorFormatException;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable value object representing a pgvector embedding.
 *
 * Serializes to the pgvector text format ("[1.0,2.0,...]") accepted by
 * PostgreSQL for `vector` columns, both when binding query parameters and
 * when casting values returned from the database.
 *
 * @implements Arrayable<int, float>
 */
final class Vector implements Arrayable, JsonSerializable
{
    /** @param list<float> $components */
    public function __construct(
        private readonly array $components,
    ) {
        if ($this->components === []) {
            throw new VectorFormatException('A vector requires at least one component.');
        }

        foreach ($this->components as $component) {
            if (! is_numeric($component)) {
                throw new VectorFormatException('Vector components must be numeric.');
            }
        }
    }

    /**
     * Parse a pgvector text representation, e.g. "[1.0, 2.0, -0.5]".
     *
     * @throws VectorFormatException
     */
    public static function fromString(string $pgvector): self
    {
        $trimmed = trim($pgvector);

        if ($trimmed === '' || $trimmed === '[...]') {
            throw new VectorFormatException('Empty vector representation.');
        }

        if (! str_starts_with($trimmed, '[') || ! str_ends_with($trimmed, ']')) {
            throw new VectorFormatException('Expected pgvector format "[1.0,2.0,...]".');
        }

        $inner = mb_substr($trimmed, 1, -1);

        $components = array_map(
            floatval(...),
            $inner === '' ? [] : explode(',', $inner),
        );

        return new self($components);
    }

    /**
     * @param  list<int|float>  $components
     */
    public static function fromArray(array $components): self
    {
        return new self($components);
    }

    /** @return list<float> */
    public function components(): array
    {
        return $this->components;
    }

    public function count(): int
    {
        return count($this->components);
    }

    /** pgvector text format expected by PostgreSQL bindings. */
    public function toPsqlString(): string
    {
        return '['.implode(',', array_map(strval(...), $this->components)).']';
    }

    /** @return array<int, float> */
    public function toArray(): array
    {
        return $this->components;
    }

    /** @return list<float> */
    public function jsonSerialize(): array
    {
        return $this->components;
    }

    public function __toString(): string
    {
        return $this->toPsqlString();
    }
}
