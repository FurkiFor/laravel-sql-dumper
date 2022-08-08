<?php

namespace Furkifor\SqlDumper;

use Furkifor\SqlDumper\SqlDumperClassInterface;
/**
 * Class SqlDumperClass
 * @package Furkifor\SqlDumper
 * @author furkanunsal69@gmail.con
 */
class SqlDumperClass implements SqlDumperClassInterface
{
    public $table;
    public $where;
    public $orderBy;
    public $limit;
    public $select;
    public $with;

    public function __construct($table)
    {
        $this->where = '1=1';
        $this->table = $table;
    }

    /**
     * @param string|string $select
     * @return $this | object
     */
    public function select(string $select = null)
    {
        if (!$select)
            $select = '*';
        $this->select = "SELECT $select FROM ";

        return $this;
    }

    /**
     * @param $variable | string
     * @param $type | string
     * @return $this | object
     */
    public function orderBy(string $variable, string $type)
    {
        $this->orderBy .= " ORDER BY $variable $type ";

        return $this;
    }

    /**
     * @param $where | string 
     * @param $condition | string 
     * @return $this | object
     */
    public function where(string $where,string $condition = 'and')
    {
        $this->where .= " $condition $where ";

        return $this;
    }

    /**
     * @param $joinParameter  = LEFT | RIGHT | INNER | string 
     * @param $condition | string 
     * @return $this | object
     */
    public function with(string $joinParameter, string $condition)
    {
        $this->with .= str_ireplace("this", $this->table, ' ' . $joinParameter . ' JOIN ' . $condition);

        return $this;
    }

    /**
     * @param $count = 1,2,3,4,5,6,7,8,9 | int 
     * @return $this | object
     */
    public function limit(int $count)
    {
        $this->limit .= " LIMIT $count ";

        return $this;
    }

    /**
     * @return mixed
     */
    public function get()
    {
       return   (@$this->select . @$this->table . @$this->with . @$this->where . @$this->orderBy . @$this->limit);
    }

    /**
     * @return mixed
     */
    public function first()
    {
       return   (@$this->select . @$this->table . @$this->with . @$this->where . @$this->orderBy . limit 1);
    }
}
