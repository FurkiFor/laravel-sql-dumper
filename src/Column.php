<?php

namespace Furkifor\SqlDumper;

use Stringable;

/**
 * A single column of a CREATE TABLE statement.
 */
class Column implements Stringable
{
    /** null = not specified, let the database decide */
    private ?bool $nullable = null;

    private bool $unique = false;

    private ?string $default = null;

    /**
     * @param string[] $constraints raw constraints written after the type
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private array $constraints = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setNullable(bool $nullable): void
    {
        $this->nullable = $nullable;
    }

    public function setUnique(): void
    {
        $this->unique = true;
    }

    /**
     * @param string $expression already formatted SQL (quoted if it is a string literal)
     */
    public function setDefault(string $expression): void
    {
        $this->default = $expression;
    }

    public function addConstraint(string $constraint): void
    {
        $this->constraints[] = $constraint;
    }

    public function __toString(): string
    {
        $parts = [$this->name, $this->type];

        if ($this->nullable !== null) {
            $parts[] = $this->nullable ? 'NULL' : 'NOT NULL';
        }
        if ($this->default !== null) {
            $parts[] = 'DEFAULT ' . $this->default;
        }
        if ($this->unique) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', array_merge($parts, $this->constraints));
    }
}
