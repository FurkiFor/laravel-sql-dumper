<?php
namespace Furkifor\SqlDumper;

interface SqlDumperClassInterface {

    public function select(string $select);
    public function orderBy(string $variable,string $type);
    public function where(string $select);
    public function with(string $joinParameter , string $condition);
    public function limit(int $count);
    public function get();

}
