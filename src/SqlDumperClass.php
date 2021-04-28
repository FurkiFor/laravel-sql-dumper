<?php

namespace Furkifor\SqlDumper;

class SqlDumperClass
{
    public $query;
    public $table;
    public $FirstWhere;
    public $where;
    public $orderBy;
    public $limit;
    public $select;
    public $whereCount;
    public $with;

    public function __construct($table)
    {
        $this->table = $table;
        $this->query = '';
        $this->select = '';
        $this->FirstWhere = '';
        $this->where = '';
        $this->orderBy = '';
        $this->limit = '';
        $this->with = '';
        $this->whereCount = 0;
    }

    public function select(string $select = '*')
    {
        $this->select = "SELECT $select FROM ";

        return $this;
    }

    public function orderBy($variable, $type)
    {
        $this->orderBy .= " ORDER BY $variable $type ";

        return $this;
    }

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

    public function with($with, $sql)
    {
        $this->with .= preg_replace('/this/i', $this->table, ' ' . $with . ' JOIN ' . $sql);

        return $this;
    }

    public function limit($count)
    {
        $this->limit .= " LIMIT $count ";

        return $this;
    }

    public function get()
    {
        return print_r(@$this->select . @$this->table . @$this->with . @$this->where . @$this->orderBy . @$this->limit)[0];
    }
}
