<?php

namespace Furkifor\SqlDumper;

use Stringable;

/**
 * A piece of SQL that is written into the query as is instead of being bound as a value.
 * Create one with SqlDumper::raw('NOW()'). Never build it from user input.
 */
final readonly class Expression implements Stringable
{
    public function __construct(private string $sql)
    {
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
