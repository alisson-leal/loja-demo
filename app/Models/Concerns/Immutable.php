<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Para registros históricos: somente inclusão, sem alteração nem exclusão.
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(fn () => throw new LogicException('Registros históricos não podem ser alterados.'));
        static::deleting(fn () => throw new LogicException('Registros históricos não podem ser apagados.'));
    }
}
