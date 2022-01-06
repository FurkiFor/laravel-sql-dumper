<?php

namespace Furkifor\SqlDumper;

/**
 * Class SqlDumperClass
 * @package Furkifor\SqlDumper
 * @author furkanunsal69@gmail.con
 */
class SqlDumperClass
{
    public $table;
    public $where;
    public $orderBy;
    public $limit;
    public $select;
    public $whereCount;
    public $with;

    public function __construct($table)
    {
        $this->table = $table;
        $this->whereCount = 0;
    }

    /**
     * @param string|string $select
     * @return $this
     */
    public function select(string $select = null)
    {
        if (!$select)
            $select = '*';
        $this->select = "SELECT $select FROM ";

        return $this;
    }

    /**
     * @param $variable
     * @param $type
     * @return $this
     */
    public function orderBy($variable, $type)
    {
        $this->orderBy .= " ORDER BY $variable $type ";

        return $this;
    }

    /**
     * @param $where
     * @return $this
     */
    public function where($where)
    {
        if ($this->whereCount == 0) {
            $this->where .= " WHERE $where ";
            $this->whereCount = 1;
        } else {
            $this->where .= " and $where ";
        }

        return $this;
    }

    /**
     * @param $joinParameter  = LEFT | RIGHT | INNER
     * @param $condition
     * @return $this
     */
    public function with($joinParameter, $condition)
    {
        $this->with .= str_ireplace("this", $this->table, ' ' . $joinParameter . ' JOIN ' . $condition);

        return $this;
    }

    /**
     * @param $count
     * @return $this
     */
    public function limit($count)
    {
        $this->limit .= " LIMIT $count ";

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSql()
    {
        return print_r(@$this->select . @$this->table . @$this->with . @$this->where . @$this->orderBy . @$this->limit)[0];
    }
}
