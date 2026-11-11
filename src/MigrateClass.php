<?php

namespace Furkifor\SqlDumper;

use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;

/**
 * Fluent CREATE TABLE builder.
 *
 * Without a connection it only produces SQL (toSql() / createTable()).
 * With a PDO connection createTable() and dropTable() also run the statement.
 */
class MigrateClass
{
    private const DRIVERS = [
        'mysql' => 'mysql',
        'mariadb' => 'mysql',
        'sqlite' => 'sqlite',
        'pgsql' => 'pgsql',
        'postgres' => 'pgsql',
        'postgresql' => 'pgsql',
        'sqlsrv' => 'sqlsrv',
        'sqlserver' => 'sqlsrv',
    ];

    private const TYPES = [
        'mysql' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'string' => 'VARCHAR(%d)',
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'boolean' => 'TINYINT(1)',
            'datetime' => 'DATETIME',
            'text' => 'TEXT',
            'float' => 'DOUBLE',
            'json' => 'JSON',
        ],
        'sqlite' => [
            'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'string' => 'VARCHAR(%d)',
            'int' => 'INTEGER',
            'bigint' => 'INTEGER',
            'boolean' => 'INTEGER',
            'datetime' => 'DATETIME',
            'text' => 'TEXT',
            'float' => 'REAL',
            'json' => 'TEXT',
        ],
        'pgsql' => [
            'id' => 'SERIAL PRIMARY KEY',
            'string' => 'VARCHAR(%d)',
            'int' => 'INTEGER',
            'bigint' => 'BIGINT',
            'boolean' => 'BOOLEAN',
            'datetime' => 'TIMESTAMP',
            'text' => 'TEXT',
            'float' => 'DOUBLE PRECISION',
            'json' => 'JSONB',
        ],
        'sqlsrv' => [
            'id' => 'INT IDENTITY(1,1) PRIMARY KEY',
            'string' => 'NVARCHAR(%d)',
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'boolean' => 'BIT',
            'datetime' => 'DATETIME2',
            'text' => 'NVARCHAR(MAX)',
            'float' => 'FLOAT',
            'json' => 'NVARCHAR(MAX)',
        ],
    ];

    private const FK_ACTIONS = ['CASCADE', 'SET NULL', 'SET DEFAULT', 'RESTRICT', 'NO ACTION'];

    private string $driver;

    private ?PDO $pdo;

    private string $table = '';

    /** @var Column[] */
    private array $columns = [];

    /** @var string[] table level constraints (FOREIGN KEY, CHECK) */
    private array $constraints = [];

    private bool $ifNotExists = false;

    /**
     * @param string $databaseType mysql, sqlite, pgsql or sqlsrv
     * @param PDO|null $pdo optional connection; without it only SQL is generated
     */
    public function __construct(string $databaseType = 'mysql', ?PDO $pdo = null)
    {
        $key = strtolower(trim($databaseType));
        if (! isset(self::DRIVERS[$key])) {
            throw new InvalidArgumentException(
                "Unsupported database type: $databaseType (supported: mysql, sqlite, pgsql, sqlsrv)"
            );
        }

        $this->driver = self::DRIVERS[$key];
        $this->pdo = $pdo;
    }

    /**
     * Start a new table. Adds an auto increment "id" primary key unless $withId is false.
     */
    public function name(string $table, bool $withId = true): static
    {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty');
        }

        $this->table = $table;
        $this->columns = [];
        $this->constraints = [];
        $this->ifNotExists = false;

        return $withId ? $this->id() : $this;
    }

    public function ifNotExists(): static
    {
        $this->ifNotExists = true;

        return $this;
    }

    // ---------------------------------------------------------------------
    // Column types
    // ---------------------------------------------------------------------

    public function id(string $name = 'id'): static
    {
        return $this->addColumn($name, $this->type('id'));
    }

    public function string(string $name, int $length = 255, array $constraints = []): static
    {
        if ($length <= 0) {
            throw new InvalidArgumentException('String length must be greater than zero');
        }

        return $this->addColumn($name, sprintf($this->type('string'), $length), $constraints);
    }

    public function int(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('int'), $constraints);
    }

    public function bigInt(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('bigint'), $constraints);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, array $constraints = []): static
    {
        if ($precision <= 0 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException("Invalid decimal precision/scale: $precision, $scale");
        }

        return $this->addColumn($name, "DECIMAL($precision, $scale)", $constraints);
    }

    public function float(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('float'), $constraints);
    }

    public function boolean(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('boolean'), $constraints);
    }

    public function date(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, 'DATE', $constraints);
    }

    public function datetime(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('datetime'), $constraints);
    }

    public function text(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('text'), $constraints);
    }

    public function json(string $name, array $constraints = []): static
    {
        return $this->addColumn($name, $this->type('json'), $constraints);
    }

    /**
     * Nullable created_at and updated_at columns.
     */
    public function timestamps(): static
    {
        return $this->datetime('created_at')->nullable()->datetime('updated_at')->nullable();
    }

    // ---------------------------------------------------------------------
    // Modifiers (apply to the last added column)
    // ---------------------------------------------------------------------

    public function notNull(): static
    {
        $this->lastColumn()->setNullable(false);

        return $this;
    }

    public function nullable(): static
    {
        $this->lastColumn()->setNullable(true);

        return $this;
    }

    public function unique(): static
    {
        $this->lastColumn()->setUnique();

        return $this;
    }

    /**
     * Default value. Numbers, booleans and null are written as is, strings are quoted.
     * Known SQL expressions (CURRENT_TIMESTAMP, NOW(), ...) are kept unquoted;
     * use defaultRaw() for anything else that must not be quoted.
     */
    public function default(mixed $value): static
    {
        if ($value === null) {
            $expression = 'NULL';
        } elseif (is_bool($value)) {
            $expression = $this->driver === 'pgsql' ? ($value ? 'TRUE' : 'FALSE') : ($value ? '1' : '0');
        } elseif (is_int($value) || is_float($value)) {
            $expression = (string) $value;
        } elseif (is_string($value)) {
            $expression = $this->isSqlExpression($value)
                ? $value
                : "'" . str_replace("'", "''", $value) . "'";
        } else {
            throw new InvalidArgumentException('Default value must be a scalar or null');
        }

        $this->lastColumn()->setDefault($expression);

        return $this;
    }

    public function defaultRaw(string $expression): static
    {
        $this->lastColumn()->setDefault($expression);

        return $this;
    }

    // ---------------------------------------------------------------------
    // Table constraints
    // ---------------------------------------------------------------------

    /**
     * Add a foreign key. If the column was not defined yet, an integer column is created for it.
     *
     * @param string|array|null $onDelete CASCADE, SET NULL, SET DEFAULT, RESTRICT or NO ACTION
     */
    public function foreignKey(
        string $name,
        string $referenceTable,
        string $referenceColumn = 'id',
        string|array|null $onDelete = null
    ): static {
        $this->requireTable();

        // Old signature had a raw column-constraint array as 4th argument.
        $columnConstraints = is_array($onDelete) ? $onDelete : [];
        $onDelete = is_array($onDelete) ? null : $onDelete;

        if (! $this->hasColumn($name)) {
            $this->int($name, $columnConstraints);
        }

        $constraint = "FOREIGN KEY ($name) REFERENCES $referenceTable($referenceColumn)";
        if ($onDelete !== null) {
            $action = strtoupper(trim($onDelete));
            if (! in_array($action, self::FK_ACTIONS, true)) {
                throw new InvalidArgumentException('Invalid ON DELETE action: ' . $onDelete);
            }
            $constraint .= ' ON DELETE ' . $action;
        }

        $this->constraints[] = $constraint;

        return $this;
    }

    /**
     * Add a CHECK constraint to the table.
     */
    public function check(string $expression): static
    {
        $this->requireTable();
        $this->constraints[] = "CHECK ($expression)";

        return $this;
    }

    // ---------------------------------------------------------------------
    // Output
    // ---------------------------------------------------------------------

    /**
     * The CREATE TABLE statement.
     */
    public function toSql(): string
    {
        $this->requireTable();
        if ($this->columns === []) {
            throw new LogicException("Table {$this->table} has no columns");
        }

        $definitions = array_merge(array_map('strval', $this->columns), $this->constraints);

        // SQL Server has no CREATE TABLE IF NOT EXISTS.
        $ifNotExists = $this->ifNotExists && $this->driver !== 'sqlsrv' ? 'IF NOT EXISTS ' : '';

        return "CREATE TABLE {$ifNotExists}{$this->table} (" . implode(', ', $definitions) . ')';
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    /**
     * Returns the CREATE TABLE statement, and runs it when a connection was given.
     */
    public function createTable(): string
    {
        $sql = $this->toSql();
        $this->run($sql);

        return $sql;
    }

    /**
     * Returns a DROP TABLE IF EXISTS statement, and runs it when a connection was given.
     */
    public function dropTable(?string $table = null): string
    {
        $table = $table ?? $this->table;
        if (trim($table) === '') {
            throw new LogicException('No table name given');
        }

        $sql = "DROP TABLE IF EXISTS $table";
        $this->run($sql);

        return $sql;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function run(string $sql): void
    {
        if ($this->pdo === null) {
            return;
        }

        if ($this->pdo->exec($sql) === false) {
            throw new RuntimeException('Migration failed: ' . implode(' ', $this->pdo->errorInfo()));
        }
    }

    private function type(string $name): string
    {
        return self::TYPES[$this->driver][$name];
    }

    private function addColumn(string $name, string $type, array $constraints = []): static
    {
        $this->requireTable();
        if (trim($name) === '') {
            throw new InvalidArgumentException('Column name cannot be empty');
        }
        if ($this->hasColumn($name)) {
            throw new InvalidArgumentException("Column $name is already defined on table {$this->table}");
        }

        $this->columns[] = new Column($name, $type, $constraints);

        return $this;
    }

    private function hasColumn(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    private function lastColumn(): Column
    {
        if ($this->columns === []) {
            throw new LogicException('Add a column before using a column modifier');
        }

        return $this->columns[count($this->columns) - 1];
    }

    private function requireTable(): void
    {
        if ($this->table === '') {
            throw new LogicException('Call name() first to set the table name');
        }
    }

    private function isSqlExpression(string $value): bool
    {
        $upper = strtoupper(trim($value));

        return in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME', 'NULL', 'TRUE', 'FALSE'], true)
            || preg_match('/^[A-Z_][A-Z0-9_]*\(.*\)$/', $upper) === 1;
    }
}
