<?php

use App\Exceptions\VectorFormatException;
use App\Values\Vector;

describe('pgvector value object', function () {
    it('round-trips the pgvector text format', function () {
        $vector = Vector::fromString('[0.1, -2.5, 3]');

        expect($vector->toPsqlString())
            ->toBe('[0.1,-2.5,3]')
            ->and($vector->count())->toBe(3)
            ->and($vector->components())->toBe([0.1, -2.5, 3.0])
            ->and((string) $vector)->toBe('[0.1,-2.5,3]');
    });

    it('builds from arrays of numerics', function () {
        expect(Vector::fromArray([1.5, 2.5])->toPsqlString())->toBe('[1.5,2.5]');
    });

    it('refuses empty or non-numeric components', function () {
        expect(fn () => Vector::fromArray([]))->toThrow(VectorFormatException::class);
        expect(fn () => Vector::fromArray([1.0, 'x']))->toThrow(VectorFormatException::class);
    });

    it('refuses malformed pgvector representations', function () {
        expect(fn () => Vector::fromString('no-vector'))
            ->toThrow(VectorFormatException::class);
        expect(fn () => Vector::fromString('[formato,incorrecto'))
            ->toThrow(VectorFormatException::class);
    });
});
