<?php

namespace Furkifor\SqlDumper\Laravel;

use Furkifor\SqlDumper\SqlDumper;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Adds rawSql() and dumpSql() to Laravel's query builder and Eloquent builder.
 */
final class BuilderMacros
{
    public static function register(): void
    {
        QueryBuilder::macro('rawSql', function (): string {
            /** @var QueryBuilder $this */
            return SqlDumper::interpolate(
                $this->toSql(),
                $this->getConnection()->prepareBindings($this->getBindings())
            );
        });

        QueryBuilder::macro('dumpSql', function () {
            /** @var QueryBuilder $this */
            echo $this->rawSql() . PHP_EOL;

            return $this;
        });

        // Registered separately so global scopes are applied and a string (not the builder) is returned.
        EloquentBuilder::macro('rawSql', function (): string {
            /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model> $this */
            return $this->toBase()->rawSql();
        });

        EloquentBuilder::macro('dumpSql', function () {
            /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model> $this */
            echo $this->toBase()->rawSql() . PHP_EOL;

            return $this;
        });
    }
}
