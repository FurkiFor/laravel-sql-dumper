# SqlDumper Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/run-tests?label=tests)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/Check%20&%20fix%20styling?label=code%20style)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)

## About The Project

This package provides tools for database interactions in PHP. It is designed as a project to get SQL query strings in ORM operations, drawing inspiration from Laravel Eloquent's logic. The package offers two main components:

*   **SQL Query Builder**: A fluent interface to construct SQL SELECT queries programmatically.
*   **Database Migration Tool**: A tool to define and create database table schemas.

## Prerequisites

This package requires PHP 7.2 or higher.

## Installation

```bash
composer require furkifor/sql_dumper
```

## SQL Query Builder Usage

```php
$sql_dumper = new Furkifor\SqlDumper("users");

// Simple query
echo $sql_dumper->select('*')->get();
// Output: SELECT * FROM users

// Query with conditions
echo $sql_dumper->select('name, email')
    ->where('age > ?', [18])
    ->orderBy('name', 'DESC')
    ->limit(10)
    ->get();
// Output: SELECT name, email FROM users WHERE age > ? ORDER BY name DESC LIMIT 10

// JOIN operations
echo $sql_dumper->select('users.*, roles.name as role_name')
    ->join('INNER', 'roles', 'users.role_id = roles.id')
    ->where('users.active = ?', [1])
    ->get();
// Output: SELECT users.*, roles.name as role_name FROM users 
// INNER JOIN roles ON users.role_id = roles.id WHERE users.active = ?

// Grouping and HAVING
echo $sql_dumper->select('country, COUNT(*) as user_count')
    ->groupBy('country')
    ->having('user_count > 100')
    ->get();
// Output: SELECT country, COUNT(*) as user_count FROM users 
// GROUP BY country HAVING user_count > 100

// Get the first user
echo $sql_dumper->select('*')->where('id = ?', [1])->first();
// Output: SELECT * FROM users WHERE id = ? LIMIT 1

// Select distinct countries
echo $sql_dumper->select('country')->distinct()->get();
// Output: SELECT DISTINCT country FROM users

// Resetting the query
$query = $sql_dumper->select('name')->where('age > ?', [18]);
echo $query->get(); // Output: SELECT name FROM users WHERE age > ?
$query->reset();
// Now the query builder is reset. You can build a new query on $sql_dumper from scratch.
// For example:
echo $sql_dumper->select('*')->where('status = ?', ['active'])->get();
// Output: SELECT * FROM users WHERE status = ?
```

### Important Notes on `execute()` and `where()`:

*   **`execute()` Method**: The `SqlDumperClass` includes an `execute()` method. However, this method is a basic implementation that instantiates its own PDO connection internally. This is not suitable for production environments or scenarios requiring configurable database connections. It also directly fetches all results. For more complex applications or production use, it is **highly recommended** to use the `get()` method to retrieve the generated SQL query string and then execute it using your application's existing database connection setup (e.g., your own configured PDO instance or other database abstraction layer).

*   **`where()` Method Safety**: When using the `where()` method, be cautious if you are embedding parameters directly into the condition string, especially for non-numeric values. The safest approach is to use `?` placeholders for values and pass an array of corresponding values as the second argument to `where()`. This helps prevent SQL injection vulnerabilities. For example, `->where('name = ?', ['John Doe'])` is preferred over `->where("name = 'John Doe'")`.

## Migration Tool Usage

The `MigrateClass` helps you define and create database tables.

```php
$table = new MigrateClass("mysql"); // Specify the database type (e.g., "mysql", "pgsql")

// Simple table creation with constraints
$table->name("users")
    ->string('username', 255, ['UNIQUE', 'NOT NULL'])
    ->string('email', 255, ['UNIQUE', 'NOT NULL'])
    ->string('password', 255, ['NOT NULL'])
    ->datetime('created_at', ['DEFAULT "CURRENT_TIMESTAMP"']) // Note: DEFAULT value needs to be a string within the array
    ->createTable();

// Table with relationships and other constraints
$table->name("posts")
    ->string('title', 255, ['NOT NULL'])
    ->text('content') // No specific constraints, column will be TEXT
    ->int('user_id', ['NOT NULL']) // Assuming user_id is INT and required
    ->foreignKey('user_id', 'users', 'id') // foreignKey() handles its own specific constraints
    ->datetime('published_at', ['NULL']) // Explicitly allowing NULL values
    ->boolean('is_published', ['DEFAULT 0']) // Example for a boolean with a default value
    ->createTable();

// Table with custom check constraints
$table->name("products")
    ->string('name', 100, ['NOT NULL'])
    ->decimal('price', "10, 2", ['NOT NULL']) // Ensure precision and scale are passed as a string if combined
    ->int('stock', ['NOT NULL', 'DEFAULT 0'])
    ->check('price > 0') // Table-level CHECK constraint
    ->check('stock >= 0') // Another table-level CHECK constraint
    ->createTable();
```

### Understanding Constraints:

When defining columns using methods like `string()`, `int()`, `datetime()`, etc., you can pass an array of constraint strings as the third argument (or second for `text`, where length is not applicable). Common constraints include:

*   `'NOT NULL'`
*   `'NULL'`
*   `'UNIQUE'`
*   `'PRIMARY KEY'` (though `id()` method is typically used for auto-incrementing primary keys)
*   `'DEFAULT <value>'` (e.g., `'DEFAULT 0'`, `'DEFAULT "CURRENT_TIMESTAMP"'`, `'DEFAULT "pending"'`)
*   For data types like `VARCHAR` (string) or `DECIMAL`, the size/precision is usually the second argument.

The `foreignKey()` and `check()` methods apply their specific types of constraints at the table level or column level as appropriate based on their design.

## Features

### SQL Query Builder
- Fluent interface to create SELECT queries
- `WHERE` conditions with parameter binding (using `?`)
- `JOIN` operations (INNER, LEFT, RIGHT)
- `ORDER BY` sorting
- `GROUP BY` grouping
- `HAVING` filtering
- `LIMIT` clause for pagination
- `DISTINCT` queries
- `first()` method to retrieve a single record (by appending `LIMIT 1`)
- `reset()` method to clear the current query state
- `get()` method to retrieve the generated SQL string

### Migration Tool
- Support for multiple database systems (e.g., MySQL, SQL Server - driver dependent).
- Common data types: `string`, `int`, `text`, `datetime`, `boolean`, `decimal`, etc.
- Automatic `id()` method for auto-incrementing primary keys.
- Fluent definition for:
    - Foreign Key relationships (`foreignKey()`)
    - Column constraints like `UNIQUE`, `NOT NULL`, `DEFAULT` (passed as an array to type methods)
    - Table-level `CHECK` constraints (`check()`)

## Future Enhancements / Roadmap

We have a documented roadmap for planned features and improvements. You can view it here: [ROADMAP.md](ROADMAP.md)

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change. Please ensure to update tests as appropriate.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Furkan Ünsal](https://github.com/FurkiFor)
- [All Contributors](../../contributors)
