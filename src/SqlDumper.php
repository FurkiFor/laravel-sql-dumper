<?php

namespace Furkifor\SqlDumper;

use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use PDO;

/**
 * Fluent SQL builder that produces SQL strings (with bound parameters)
 * and can optionally run them through PDO.
 *
 * Values are always bound as parameters. Table and column names are written
 * into the SQL as given, so never pass untrusted input as an identifier.
 *
 * @author furkanunsal69@gmail.com
 */
class SqlDumper implements SqlDumperClassInterface
{
    private const OPERATORS = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'REGEXP', 'NOT REGEXP',
    ];

    private const JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'];

    private const NO_BINDINGS = ['select' => [], 'write' => [], 'join' => [], 'where' => [], 'having' => []];

    protected string $table;

    protected ?PDO $pdo;

    /** select | insert | update | delete */
    protected string $type = 'select';

    /** @var string[] */
    protected array $columns = [];

    protected bool $distinct = false;

    /** @var string[] */
    protected array $joins = [];

    /** @var array<int, array{0: string, 1: string}> [boolean, sql] */
    protected array $wheres = [];

    /** @var string[] */
    protected array $groups = [];

    /** @var string[] */
    protected array $havings = [];

    /** @var string[] */
    protected array $orders = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /** @var string[] columns of an INSERT / UPDATE */
    protected array $writeColumns = [];

    /** @var string[] INSERT: one "(?, ?)" tuple per row. UPDATE: one right-hand side per column. */
    protected array $writeValues = [];

    /** @var array<string, array<int, mixed>> */
    protected array $bindings = self::NO_BINDINGS;

    final public function __construct(string $table, ?PDO $pdo = null)
    {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty');
        }

        $this->table = $table;
        $this->pdo = $pdo;
    }

    /**
     * Static shortcut: SqlDumper::table('users')->where('id', 1)->get()
     *
     * @return static
     */
    public static function table(string $table, ?PDO $pdo = null): static
    {
        return new static($table, $pdo);
    }

    /**
     * Set the PDO connection used by execute().
     */
    public function connection(PDO $pdo): static
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * SQL that must be written as is instead of being bound as a value:
     *
     *   ->update(['updated_at' => SqlDumper::raw('NOW()')])
     *   ->where('expires_at', '<', SqlDumper::raw('CURRENT_DATE'))
     */
    public static function raw(string $sql): Expression
    {
        return new Expression($sql);
    }

    // ---------------------------------------------------------------------
    // SELECT
    // ---------------------------------------------------------------------

    /**
     * Columns to select. Accepts 'a, b', 'a', 'b' or ['a', 'b'].
     *
     * @param string|string[] ...$columns
     */
    public function select(...$columns): static
    {
        $this->type = 'select';
        $this->columns = $this->flattenColumns($columns);
        $this->bindings['select'] = [];

        return $this;
    }

    /**
     * Add a raw select expression with optional parameters:
     *
     *   ->selectRaw('price * ? AS price_with_tax', [1.2])
     *
     * @param array<int, mixed> $params
     */
    public function selectRaw(string $expression, array $params = []): static
    {
        $this->columns[] = $expression;
        $this->bindings['select'] = array_merge($this->bindings['select'], array_values($params));

        return $this;
    }

    /**
     * Add more columns to the current selection.
     *
     * @param string|string[] ...$columns
     */
    public function addSelect(...$columns): static
    {
        $this->columns = array_merge($this->columns, $this->flattenColumns($columns));

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    // ---------------------------------------------------------------------
    // WHERE
    // ---------------------------------------------------------------------

    /**
     * Add a WHERE condition. Supported forms:
     *
     *   where('age', 18)                    age = ?
     *   where('age', '>', 18)               age > ?
     *   where('deleted_at', null)           deleted_at IS NULL
     *   where('age > ? AND x = ?', [18, 1]) raw condition with parameters
     *   where('active = 1')                 raw condition
     *   where(function ($q) { ... })        nested group in parentheses
     *
     */
    public function where(string|Closure $column, mixed $operator = [], mixed $value = null, string $boolean = 'AND'): static
    {
        $argc = func_num_args();

        if ($column instanceof Closure) {
            $boolean = ($argc === 2 && is_string($operator)) ? $operator : $boolean;

            return $this->whereNested($column, $boolean);
        }

        if (trim($column) === '') {
            throw new InvalidArgumentException('Where column/condition cannot be empty');
        }

        // Raw form: where('age > ?', [18]) / where('age > ?', [18], 'OR') / where('active = 1')
        if ($argc === 1 || is_array($operator)) {
            if ($argc === 3 && is_string($value)) {
                $boolean = $value;
            }

            return $this->whereRaw($column, $argc === 1 ? [] : $operator, $boolean);
        }

        // where('age', 18)
        if ($argc === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper(trim((string) $operator));
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException('Invalid where operator: ' . $operator);
        }

        if ($value === null) {
            if ($operator === '=') {
                return $this->whereNull($column, $boolean);
            }
            if ($operator === '!=' || $operator === '<>') {
                return $this->whereNotNull($column, $boolean);
            }

            throw new InvalidArgumentException("Operator $operator cannot be used with a null value");
        }

        if ($value instanceof Expression) {
            return $this->addWhere("$column $operator $value", [], $boolean);
        }

        return $this->addWhere("$column $operator ?", [$value], $boolean);
    }

    /**
     * Compare two columns: whereColumn('updated_at', '>', 'created_at').
     * With two arguments the operator is "=".
     */
    public function whereColumn(string $first, string $operator, ?string $second = null, string $boolean = 'AND'): static
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $operator = strtoupper(trim($operator));
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException('Invalid where operator: ' . $operator);
        }

        return $this->addWhere("$first $operator $second", [], $boolean);
    }

    /**
     * WHERE EXISTS (subquery).
     */
    public function whereExists(self $query, string $boolean = 'AND', bool $not = false): static
    {
        return $this->addWhere(($not ? 'NOT EXISTS (' : 'EXISTS (') . $query->get() . ')', $query->getBindings(), $boolean);
    }

    public function whereNotExists(self $query, string $boolean = 'AND'): static
    {
        return $this->whereExists($query, $boolean, true);
    }

    /**
     * Same as where() but joined with OR.
     *
     */
    public function orWhere(string|Closure $column, mixed $operator = [], mixed $value = null): static
    {
        $argc = func_num_args();

        if ($column instanceof Closure) {
            return $this->whereNested($column, 'OR');
        }
        if ($argc === 1 || is_array($operator)) {
            return $this->whereRaw($column, $argc === 1 ? [] : $operator, 'OR');
        }
        if ($argc === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }

        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereRaw(string $condition, array $params = [], string $boolean = 'AND'): static
    {
        return $this->addWhere($condition, $params, $boolean);
    }

    /**
     * @param array<int|string, mixed>|self $values a list of values or a subquery
     */
    public function whereIn(string $column, array|self $values, string $boolean = 'AND', bool $not = false): static
    {
        if ($values instanceof self) {
            return $this->addWhere(
                $column . ($not ? ' NOT IN (' : ' IN (') . $values->get() . ')',
                $values->getBindings(),
                $boolean
            );
        }

        if ($values === []) {
            // IN () is invalid SQL: an empty list matches nothing (or everything for NOT IN).
            return $this->addWhere($not ? '1 = 1' : '0 = 1', [], $boolean);
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return $this->addWhere(
            $column . ($not ? ' NOT IN (' : ' IN (') . $placeholders . ')',
            array_values($values),
            $boolean
        );
    }

    /**
     * @param array<int|string, mixed>|self $values a list of values or a subquery
     */
    public function whereNotIn(string $column, array|self $values, string $boolean = 'AND'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'AND'): static
    {
        return $this->addWhere("$column IS NULL", [], $boolean);
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        return $this->addWhere("$column IS NOT NULL", [], $boolean);
    }

    /**
     * @param array $range exactly two values: [from, to]
     */
    public function whereBetween(string $column, array $range, string $boolean = 'AND'): static
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('whereBetween expects exactly two values');
        }

        return $this->addWhere("$column BETWEEN ? AND ?", array_values($range), $boolean);
    }

    public function whereLike(string $column, string $pattern, string $boolean = 'AND'): static
    {
        return $this->addWhere("$column LIKE ?", [$pattern], $boolean);
    }

    /**
     * Apply the callback only when $condition is truthy.
     *
     *   ->when($request->role, fn ($q, $role) => $q->where('role', $role))
     *
     */
    public function when(mixed $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this, $condition);
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    // JOIN / GROUP / ORDER / LIMIT
    // ---------------------------------------------------------------------

    public function join(string $type, string $table, string $on = '', array $params = []): static
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, self::JOIN_TYPES, true)) {
            throw new InvalidArgumentException('Invalid join type: ' . $type);
        }
        if ($type !== 'CROSS' && trim($on) === '') {
            throw new InvalidArgumentException("$type JOIN requires an ON condition");
        }

        $this->joins[] = "$type JOIN $table" . ($on !== '' ? " ON $on" : '');
        $this->bindings['join'] = array_merge($this->bindings['join'], array_values($params));

        return $this;
    }

    public function innerJoin(string $table, string $on, array $params = []): static
    {
        return $this->join('INNER', $table, $on, $params);
    }

    public function leftJoin(string $table, string $on, array $params = []): static
    {
        return $this->join('LEFT', $table, $on, $params);
    }

    public function rightJoin(string $table, string $on, array $params = []): static
    {
        return $this->join('RIGHT', $table, $on, $params);
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);

        return $this;
    }

    public function having(string $condition, array $params = []): static
    {
        $this->havings[] = $condition;
        $this->bindings['having'] = array_merge($this->bindings['having'], array_values($params));

        return $this;
    }

    /**
     * Add an ORDER BY column. Can be called several times.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper(trim($direction));
        if (! in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid order direction: ' . $direction);
        }

        $this->orders[] = "$column $direction";

        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Newest first: ORDER BY created_at DESC.
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Oldest first: ORDER BY created_at ASC.
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $count): static
    {
        if ($count <= 0) {
            throw new InvalidArgumentException('Limit count must be greater than zero');
        }

        $this->limit = $count;

        return $this;
    }

    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * LIMIT/OFFSET for a 1-based page number.
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->limit($perPage)->offset((max($page, 1) - 1) * $perPage);
    }

    // ---------------------------------------------------------------------
    // INSERT / UPDATE / DELETE
    // ---------------------------------------------------------------------

    /**
     * Build an INSERT. Pass one row (['name' => 'a']) or a list of rows.
     */
    public function insert(array $values): static
    {
        if ($values === []) {
            throw new InvalidArgumentException('Insert values cannot be empty');
        }

        $rows = is_array(reset($values)) ? array_values($values) : [$values];
        $columns = array_keys($rows[0]);
        if ($columns === [] || array_filter($columns, 'is_string') !== $columns) {
            throw new InvalidArgumentException('Insert rows must be column => value arrays');
        }

        $bindings = [];
        $tuples = [];
        foreach ($rows as $row) {
            if (! is_array($row) || array_keys($row) !== $columns) {
                throw new InvalidArgumentException('All insert rows must have the same columns in the same order');
            }
            $tuples[] = '(' . implode(', ', $this->placeholders($row, $bindings)) . ')';
        }

        $this->type = 'insert';
        $this->writeColumns = $columns;
        $this->writeValues = $tuples;
        $this->bindings['write'] = $bindings;

        return $this;
    }

    /**
     * Build an UPDATE using the current WHERE conditions.
     * Use SqlDumper::raw() for values that are SQL expressions.
     */
    public function update(array $values): static
    {
        if ($values === [] || array_filter(array_keys($values), 'is_string') !== array_keys($values)) {
            throw new InvalidArgumentException('Update values must be a non-empty column => value array');
        }

        $bindings = [];

        $this->type = 'update';
        $this->writeColumns = array_keys($values);
        $this->writeValues = $this->placeholders($values, $bindings);
        $this->bindings['write'] = $bindings;

        return $this;
    }

    /**
     * UPDATE ... SET column = column + ?. $extra holds other columns to update in the same statement.
     *
     * @param array<string, mixed> $extra
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): static
    {
        return $this->adjust($column, '+', $amount, $extra);
    }

    /**
     * UPDATE ... SET column = column - ?.
     *
     * @param array<string, mixed> $extra
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): static
    {
        return $this->adjust($column, '-', $amount, $extra);
    }

    /**
     * Build a DELETE using the current WHERE conditions.
     */
    public function delete(): static
    {
        $this->type = 'delete';
        $this->bindings['write'] = [];

        return $this;
    }

    // ---------------------------------------------------------------------
    // OUTPUT
    // ---------------------------------------------------------------------

    /**
     * The SQL with ? placeholders.
     */
    public function get(): string
    {
        return $this->compile(false);
    }

    /**
     * The raw SQL (values filled in) with one clause per line, for reading long queries.
     */
    public function toPrettySql(): string
    {
        return static::interpolate($this->compile(true), $this->getBindings());
    }

    /**
     * Alias of get(), same name Laravel uses.
     */
    public function toSql(): string
    {
        return $this->get();
    }

    /**
     * The same query limited to one row. Does not modify this builder.
     */
    public function first(): string
    {
        $query = clone $this;

        return $query->limit(1)->get();
    }

    /**
     * A new query selecting COUNT(...) AS aggregate with the current joins and conditions.
     * This builder is not modified.
     *
     * @return static
     */
    public function count(string $column = '*'): static
    {
        return $this->aggregate('COUNT', $column);
    }

    /** @return static */
    public function sum(string $column): static
    {
        return $this->aggregate('SUM', $column);
    }

    /** @return static */
    public function avg(string $column): static
    {
        return $this->aggregate('AVG', $column);
    }

    /** @return static */
    public function min(string $column): static
    {
        return $this->aggregate('MIN', $column);
    }

    /** @return static */
    public function max(string $column): static
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Bound parameters in the order they appear in the SQL.
     */
    public function getBindings(): array
    {
        $b = $this->bindings;

        return match ($this->type) {
            'insert' => $b['write'],
            'update' => array_merge($b['join'], $b['write'], $b['where']),
            'delete' => $b['where'],
            default => array_merge($b['select'], $b['join'], $b['where'], $b['having']),
        };
    }

    /**
     * The SQL with parameters written in place. For debugging and logging only;
     * run queries with execute() or with get() + getBindings().
     */
    public function toRawSql(): string
    {
        return static::interpolate($this->get(), $this->getBindings());
    }

    /**
     * Print the raw SQL and keep chaining. Pass true for one clause per line.
     */
    public function dump(bool $pretty = false): static
    {
        echo ($pretty ? $this->toPrettySql() : $this->toRawSql()) . PHP_EOL;

        return $this;
    }

    /**
     * Print the raw SQL and stop the script.
     *
     */
    public function dd(bool $pretty = false): never
    {
        $this->dump($pretty);

        exit(1);
    }

    public function __toString(): string
    {
        return $this->get();
    }

    /**
     * Put bindings into a SQL string. Works with any query, e.g. a Laravel one:
     *
     *   SqlDumper::interpolate($query->toSql(), $query->getBindings());
     *
     * Supports ? and :named placeholders and skips quoted text.
     * The result is meant to be read, not executed.
     */
    public static function interpolate(string $sql, array $bindings): string
    {
        $named = [];
        $positional = [];
        foreach ($bindings as $key => $value) {
            if (is_string($key)) {
                $named[ltrim($key, ':')] = $value;
            } else {
                $positional[] = $value;
            }
        }

        $result = '';
        $quote = null;
        $index = 0;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $result .= $char;
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $result .= $char;

                continue;
            }

            // "??" is an escaped question mark (Laravel uses it for PostgreSQL JSON operators).
            if ($char === '?' && $i + 1 < $length && $sql[$i + 1] === '?') {
                $result .= '?';
                $i++;

                continue;
            }

            if ($char === '?' && array_key_exists($index, $positional)) {
                $result .= static::quoteValue($positional[$index++]);

                continue;
            }

            if ($char === ':' && ($i === 0 || $sql[$i - 1] !== ':')
                && preg_match('/\G:([A-Za-z_][A-Za-z0-9_]*)/', $sql, $match, 0, $i)
                && array_key_exists($match[1], $named)
            ) {
                $result .= static::quoteValue($named[$match[1]]);
                $i += strlen($match[1]);

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Run the query. SELECT returns the rows, INSERT/UPDATE/DELETE the affected row count.
     *
     * The connection is taken from the argument, then from the constructor /
     * connection(), then from Laravel's DB facade when running inside Laravel.
     *
     */
    public function execute(?PDO $pdo = null): array|int
    {
        $pdo = $pdo ?? $this->pdo ?? $this->laravelPdo();
        if ($pdo === null) {
            throw new LogicException(
                'No database connection. Pass a PDO instance to execute(), connection() or the constructor.'
            );
        }

        $statement = $pdo->prepare($this->get());
        if ($statement === false) {
            throw new \RuntimeException('Could not prepare query: ' . implode(' ', $pdo->errorInfo()));
        }

        foreach ($this->getBindings() as $position => $value) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            $statement->bindValue($position + 1, $value, $this->pdoType($value));
        }

        if ($statement->execute() === false) {
            throw new \RuntimeException('Query failed: ' . implode(' ', $statement->errorInfo()));
        }

        return $this->type === 'select'
            ? $statement->fetchAll(PDO::FETCH_ASSOC)
            : $statement->rowCount();
    }

    /**
     * Clear everything except the table name and connection.
     */
    public function reset(): void
    {
        $this->type = 'select';
        $this->columns = [];
        $this->distinct = false;
        $this->joins = [];
        $this->wheres = [];
        $this->groups = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->writeColumns = [];
        $this->writeValues = [];
        $this->bindings = self::NO_BINDINGS;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    protected function addWhere(string $sql, array $params, string $boolean): static
    {
        $boolean = strtoupper(trim($boolean));
        if (! in_array($boolean, ['AND', 'OR'], true)) {
            throw new InvalidArgumentException('Invalid condition operator: ' . $boolean);
        }

        $this->wheres[] = [$boolean, $sql];
        $this->bindings['where'] = array_merge($this->bindings['where'], array_values($params));

        return $this;
    }

    protected function whereNested(Closure $callback, string $boolean): static
    {
        $nested = new static($this->table);
        $callback($nested);

        if ($nested->wheres === []) {
            return $this;
        }

        return $this->addWhere('(' . $nested->compileConditions() . ')', $nested->bindings['where'], $boolean);
    }

    protected function compileConditions(bool $pretty = false): string
    {
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $condition]) {
            $sql .= ($i === 0 ? '' : ($pretty ? "\n  $boolean " : " $boolean ")) . $condition;
        }

        return $sql;
    }

    /**
     * Build the statement from its clauses: joined by spaces, or by new lines when $pretty.
     */
    protected function compile(bool $pretty): string
    {
        $clauses = match ($this->type) {
            'insert' => $this->insertClauses(),
            'update' => $this->updateClauses($pretty),
            'delete' => $this->deleteClauses($pretty),
            default => $this->selectClauses($pretty),
        };

        return implode($pretty ? "\n" : ' ', $clauses);
    }

    /**
     * @return string[]
     */
    protected function selectClauses(bool $pretty): array
    {
        $columns = $this->columns === [] ? '*' : implode(', ', $this->columns);

        return array_merge(
            ['SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $columns, 'FROM ' . $this->table],
            $this->joins,
            $this->whereClauses($pretty),
            $this->groups === [] ? [] : ['GROUP BY ' . implode(', ', $this->groups)],
            $this->havings === [] ? [] : ['HAVING ' . implode(' AND ', $this->havings)],
            $this->tailClauses(),
            $this->offset === null ? [] : ['OFFSET ' . $this->offset]
        );
    }

    /**
     * @return string[]
     */
    protected function insertClauses(): array
    {
        return [
            'INSERT INTO ' . $this->table . ' (' . implode(', ', $this->writeColumns) . ')',
            'VALUES ' . implode(', ', $this->writeValues),
        ];
    }

    /**
     * @return string[]
     */
    protected function updateClauses(bool $pretty): array
    {
        $assignments = [];
        foreach ($this->writeColumns as $i => $column) {
            $assignments[] = $column . ' = ' . $this->writeValues[$i];
        }

        return array_merge(
            ['UPDATE ' . $this->table],
            $this->joins,
            ['SET ' . implode(', ', $assignments)],
            $this->whereClauses($pretty),
            $this->tailClauses()
        );
    }

    /**
     * @return string[]
     */
    protected function deleteClauses(bool $pretty): array
    {
        if ($this->joins !== []) {
            throw new LogicException('DELETE with JOIN is not supported; use whereIn() / whereExists() with a subquery');
        }

        return array_merge(['DELETE FROM ' . $this->table], $this->whereClauses($pretty), $this->tailClauses());
    }

    /**
     * @return string[]
     */
    protected function whereClauses(bool $pretty): array
    {
        return $this->wheres === [] ? [] : ['WHERE ' . $this->compileConditions($pretty)];
    }

    /**
     * @return string[]
     */
    protected function tailClauses(): array
    {
        return array_merge(
            $this->orders === [] ? [] : ['ORDER BY ' . implode(', ', $this->orders)],
            $this->limit === null ? [] : ['LIMIT ' . $this->limit]
        );
    }

    /**
     * @return static
     */
    protected function aggregate(string $function, string $column): static
    {
        $query = clone $this;
        $query->type = 'select';
        $query->columns = ["$function($column) AS aggregate"];
        $query->distinct = false;
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;
        $query->writeColumns = [];
        $query->writeValues = [];
        $query->bindings['select'] = [];
        $query->bindings['write'] = [];

        return $query;
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function adjust(string $column, string $sign, int|float $amount, array $extra): static
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Increment/decrement amount cannot be negative');
        }

        $this->update(array_merge([$column => $amount], $extra));
        $this->writeValues[0] = "$column $sign ?";

        return $this;
    }

    /**
     * Turn values into "?" placeholders (collecting the bindings) and raw expressions into SQL.
     *
     * @param array<int|string, mixed> $values
     * @param array<int, mixed> $bindings
     * @return string[]
     */
    protected function placeholders(array $values, array &$bindings): array
    {
        $placeholders = [];
        foreach ($values as $value) {
            if ($value instanceof Expression) {
                $placeholders[] = (string) $value;
            } else {
                $placeholders[] = '?';
                $bindings[] = $value;
            }
        }

        return $placeholders;
    }

    /**
     * @param array $columns
     * @return string[]
     */
    protected function flattenColumns(array $columns): array
    {
        $flat = [];
        foreach ($columns as $column) {
            foreach ((array) $column as $name) {
                if (! is_string($name) || trim($name) === '') {
                    throw new InvalidArgumentException('Column names must be non-empty strings');
                }
                $flat[] = trim($name);
            }
        }

        return $flat;
    }

    protected static function quoteValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => "'" . $value->format('Y-m-d H:i:s') . "'",
            is_scalar($value), $value instanceof \Stringable => "'" . str_replace("'", "''", (string) $value) . "'",
            default => throw new InvalidArgumentException('Cannot write a ' . get_debug_type($value) . ' into SQL'),
        };
    }

    protected function pdoType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }

    protected function laravelPdo(): ?PDO
    {
        $facade = '\Illuminate\Support\Facades\DB';
        if (! class_exists($facade)) {
            return null;
        }

        try {
            $pdo = $facade::connection()->getPdo();

            return $pdo instanceof PDO ? $pdo : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
