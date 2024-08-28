<?php

namespace Furkifor\SqlDumper;

use Furkifor\SqlDumper\SqlDumperClassInterface;

/**
 * Class SqlDumperClass
 * @package Furkifor\SqlDumper
 * @author furkanunsal69@gmail.com
 */
class SqlDumperClass implements SqlDumperClassInterface
{
    private $table;
    private $where = [];
    private $orderBy = '';
    private $limit = '';
    private $select = '';
    private $joins = '';
    private $groupBy = '';
    private $having = '';
    private $params = [];
    private $distinct = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Set the columns to select.
     * 
     * @param string|null $select
     * @return self
     */
    public function select(string $select = '*'): self
    {
        $this->select = "SELECT " . ($this->distinct ? 'DISTINCT ' : '') . "$select FROM $this->table";
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     * 
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException('Invalid order direction: ' . $direction);
        }
        $this->orderBy = " ORDER BY $column $direction";
        return $this;
    }

    /**
     * Add a WHERE clause.
     * 
     * @param string $condition
     * @param array $params
     * @param string $operator
     * @return self
     */
    public function where(string $condition, array $params = [], string $operator = 'AND'): self
    {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['AND', 'OR'])) {
            throw new \InvalidArgumentException('Invalid condition operator: ' . $operator);
        }
        $this->where[] = "$operator $condition";
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Add a JOIN clause.
     * 
     * @param string $type
     * @param string $table
     * @param string $on
     * @return self
     */
    public function join(string $type, string $table, string $on): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['LEFT', 'RIGHT', 'INNER'])) {
            throw new \InvalidArgumentException('Invalid join type: ' . $type);
        }
        $this->joins .= " $type JOIN $table ON $on";
        return $this;
    }

    /**
     * Set the LIMIT clause.
     * 
     * @param int $count
     * @return self
     */
    public function limit(int $count): self
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Limit count must be greater than zero');
        }
        $this->limit = " LIMIT $count";
        return $this;
    }

    /**
     * Add a GROUP BY clause.
     * 
     * @param string $column
     * @return self
     */
    public function groupBy(string $column): self
    {
        $this->groupBy = " GROUP BY $column";
        return $this;
    }

    /**
     * Add a HAVING clause.
     * 
     * @param string $condition
     * @return self
     */
    public function having(string $condition): self
    {
        $this->having = " HAVING $condition";
        return $this;
    }

    /**
     * Set the query to use DISTINCT.
     * 
     * @return self
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Build and return the SQL query.
     * 
     * @return string
     */
    public function get(): string
    {
        $whereClause = !empty($this->where) ? ' WHERE ' . implode(' ', $this->where) : '';
        return trim($this->select . $this->joins . $whereClause . $this->groupBy . $this->having . $this->orderBy . $this->limit);
    }

    /**
     * Get the first result.
     * 
     * @return string
     */
    public function first(): string
    {
        $this->limit(1);
        return $this->get();
    }

    /**
     * Reset the query parameters to their default state.
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->where = [];
        $this->orderBy = '';
        $this->limit = '';
        $this->select = '';
        $this->joins = '';
        $this->groupBy = '';
        $this->having = '';
        $this->params = [];
        $this->distinct = false;
    }

    /**
     * Execute the query with bound parameters.
     * 
     * @return mixed
     */
    public function execute()
    {
        // Assuming you have a PDO instance called $pdo
        $pdo = new \PDO(/* DSN */, /* Username */, /* Password */);
        $stmt = $pdo->prepare($this->get());
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }
}
