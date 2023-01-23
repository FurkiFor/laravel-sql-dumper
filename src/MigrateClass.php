<?php
namespace Furkifor\MigrateClass;

/**
 * Class MigrateClass
 * @package Furkifor\SqlDumper
 * @author furkanunsal69@gmail.con
 */
class MigrateClass
{
    private $conn;
    private $table;
    private $columns = array();

    public function __construct($databaseType)
    {
        $config = parse_ini_file('config.ini');
        $servername = $config['servername'];
        $username = $config['username'];
        $password = $config['password'];
        $dbname = $config['dbname'];
        if ($databaseType == 'mysql') {
            $this->conn = new mysqli($servername, $username, $password, $dbname);
        } elseif ($databaseType == 'mongodb') {
            // Connect to MongoDB
        } elseif ($databaseType == 'sqlserver') {
            // Connect to SQL Server
        }
    }

    public function name($table)
    {
        $this->table = $table;
        $this->columns[] = new Column("id", "INT(11)",["AUTO_INCREMENT","PRIMARY KEY","NOT NULL"]);
        return $this;
    }

    public function string($name, $length, $constraints=[])
    {
        $column = new Column($name, "VARCHAR($length)",$constraints);
        array_push($this->columns, $column);
        return $column;
    }
    public function int($name, $constraints=[])
    {
        $column = new Column($name, "INT",$constraints);
        array_push($this->columns, $column);
        return $column;
    }

    public function datetime($name, $constraints=[])
    {
        $column = new Column($name, "DATETIME",$constraints);
        array_push($this->columns, $column);
        return $column;
    }
    public function foreignKey($name,$referenceTable,$referenceColumn,$constraints=[])
    {
        $column = new Column($name, "INT", array_merge($constraints,["FOREIGN KEY ($name) REFERENCES $referenceTable($referenceColumn)"]));
        array_push($this->columns, $column);
        return $column;
    }

    public function check($check)
    {
        $this->columns[count($this->columns)-1]->addConstraint("CHECK ($check)");
    }
    public function default($default)
    {
        $this->columns[count($this->columns)-1]->addConstraint("DEFAULT $default");
    }

    public function createTable()
    {
        $columnsString = implode(',', $this->columns);
        $sql = "CREATE TABLE {$this->table} ($columnsString)";
        if ($this->conn->query($sql) === TRUE) {
            echo "Table {$this->table} created successfully";
        } else {
            echo "Error creating table: " . $this->conn->error;
        }
    }
}

class Column
{
    private $name;
    private $type;
    private $constraints = array();

    public function __construct($name, $type,$constraints)
    {
        $this->name = $name;
        $this->type = $type;
        $this->constraints = $constraints;
    }

    public function addConstraint($constraint)
    {
        array_push($this->constraints, $constraint);
    }
}