<?php
namespace Furkifor\MigrateClass;

use mysqli;
use Exception;

/**
 * Class MigrateClass
 * @package Furkifor\SqlDumper
 * Handles database migrations.
 */
class MigrateClass
{
    private $conn;
    private $table;
    private $columns = [];

    public function __construct($databaseType)
    {
        $this->connect($databaseType);
    }

    /**
     * Establish a database connection based on the provided type.
     * 
     * @param string $databaseType
     * @throws Exception
     */
    private function connect($databaseType)
    {
        $config = parse_ini_file('config.ini');
        $servername = $config['servername'];
        $username = $config['username'];
        $password = $config['password'];
        $dbname = $config['dbname'];

        switch ($databaseType) {
            case 'mysql':
                $this->conn = new mysqli($servername, $username, $password, $dbname);
                if ($this->conn->connect_error) {
                    throw new Exception("MySQL Connection failed: " . $this->conn->connect_error);
                }
                break;
            case 'mongodb':
                // Connect to MongoDB
                break;
            case 'sqlserver':
                // Connect to SQL Server
                break;
            default:
                throw new Exception("Unsupported database type: $databaseType");
        }
    }

    /**
     * Set the table name and initialize with an ID column.
     * 
     * @param string $table
     * @return self
     */
    public function name($table): self
    {
        $this->table = $table;
        $this->columns[] = new Column("id", "INT(11)", ["AUTO_INCREMENT", "PRIMARY KEY", "NOT NULL"]);
        return $this;
    }

    /**
     * Define a string column.
     * 
     * @param string $name
     * @param int $length
     * @param array $constraints
     * @return self
     */
    public function string($name, $length = 255, $constraints = []): self
    {
        $this->columns[] = new Column($name, "VARCHAR($length)", $constraints);
        return $this;
    }

    /**
     * Define an integer column.
     * 
     * @param string $name
     * @param array $constraints
     * @return self
     */
    public function int($name, $constraints = []): self
    {
        $this->columns[] = new Column($name, "INT", $constraints);
        return $this;
    }

    /**
     * Define a boolean column.
     * 
     * @param string $name
     * @param array $constraints
     * @return self
     */
    public function boolean($name, $constraints = []): self
    {
        $this->columns[] = new Column($name, "TINYINT(1)", $constraints);
        return $this;
    }

    /**
     * Define a datetime column.
     * 
     * @param string $name
     * @param array $constraints
     * @return self
     */
    public function datetime($name, $constraints = []): self
    {
        $this->columns[] = new Column($name, "DATETIME", $constraints);
        return $this;
    }

    /**
     * Define a text column.
     * 
     * @param string $name
     * @param array $constraints
     * @return self
     */
    public function text($name, $constraints = []): self
    {
        $this->columns[] = new Column($name, "TEXT", $constraints);
        return $this;
    }

    /**
     * Define a foreign key column.
     * 
     * @param string $name
     * @param string $referenceTable
     * @param string $referenceColumn
     * @param array $constraints
     * @return self
     */
    public function foreignKey($name, $referenceTable, $referenceColumn, $constraints = []): self
    {
        $constraint = "FOREIGN KEY ($name) REFERENCES $referenceTable($referenceColumn)";
        $this->columns[] = new Column($name, "INT", array_merge($constraints, [$constraint]));
        return $this;
    }

    /**
     * Add a CHECK constraint to the last added column.
     * 
     * @param string $check
     * @return self
     */
    public function check($check): self
    {
        $this->columns[count($this->columns) - 1]->addConstraint("CHECK ($check)");
        return $this;
    }

    /**
     * Add a DEFAULT constraint to the last added column.
     * 
     * @param string $default
     * @return self
     */
    public function default($default): self
    {
        $this->columns[count($this->columns) - 1]->addConstraint("DEFAULT $default");
        return $this;
    }

    /**
     * Create the table based on the defined columns.
     * 
     * @throws Exception
     */
    public function createTable(): void
    {
        $columnsString = implode(',', $this->columns);
        $sql = "CREATE TABLE {$this->table} ($columnsString)";
        
        if ($this->conn->query($sql) === TRUE) {
            echo "Table {$this->table} created successfully";
        } else {
            throw new Exception("Error creating table: " . $this->conn->error);
        }
    }
}

/**
 * Class representing a database column.
 */
class Column
{
    private $name;
    private $type;
    private $constraints = [];

    /**
     * Column constructor.
     * 
     * @param string $name
     * @param string $type
     * @param array $constraints
     */
    public function __construct($name, $type, $constraints = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->constraints = $constraints;
    }

    /**
     * Add a constraint to the column.
     * 
     * @param string $constraint
     * @return void
     */
    public function addConstraint($constraint): void
    {
        $this->constraints[] = $constraint;
    }

    /**
     * Convert the column to a string representation.
     * 
     * @return string
     */
    public function __toString(): string
    {
        return "{$this->name} {$this->type} " . implode(' ', $this->constraints);
    }
}
