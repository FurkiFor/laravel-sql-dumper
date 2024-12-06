# SqlDumper Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/run-tests?label=tests)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/Check%20&%20fix%20styling?label=code%20style)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)

SQL query builder and database migration tool.

## Installation

```bash
composer require furkifor/sql_dumper
```

## SQL Query Builder Usage

```php
$sql_dumper = new Furkifor\SqlDumper("users");

// Simple query
echo $sql_dumper->select('*')->get();
// SELECT * FROM users

// Query with conditions
echo $sql_dumper->select('name, email')
    ->where('age > ?', [18])
    ->orderBy('name', 'DESC')
    ->limit(10)
    ->get();
// SELECT name, email FROM users WHERE age > ? ORDER BY name DESC LIMIT 10

// JOIN operations
echo $sql_dumper->select('users.*, roles.name as role_name')
    ->join('INNER', 'roles', 'users.role_id = roles.id')
    ->where('users.active = ?', [1])
    ->get();
// SELECT users.*, roles.name as role_name FROM users 
// INNER JOIN roles ON users.role_id = roles.id WHERE users.active = ?

// Grouping and HAVING
echo $sql_dumper->select('country, COUNT(*) as user_count')
    ->groupBy('country')
    ->having('user_count > 100')
    ->get();
// SELECT country, COUNT(*) as user_count FROM users 
// GROUP BY country HAVING user_count > 100
```

## Migration Tool Usage

```php
$table = new MigrateClass("mysql");

// Simple table creation
$table->name("users")
    ->string('username', 255)->unique()->notnull()
    ->string('email', 255)->unique()->notnull()
    ->string('password', 255)->notnull()
    ->datetime('created_at')->default("CURRENT_TIMESTAMP")
    ->createTable();

// Table with relationships
$table->name("posts")
    ->string('title', 255)->notnull()
    ->text('content')
    ->int('user_id')->notnull()
    ->foreignKey('user_id', 'users', 'id')
    ->datetime('published_at')->nullable()
    ->boolean('is_published')->default(0)
    ->createTable();

// Table with custom constraints
$table->name("products")
    ->string('name', 100)->notnull()
    ->decimal('price', 10, 2)->notnull()
    ->int('stock')->notnull()->default(0)
    ->check('price > 0')
    ->check('stock >= 0')
    ->createTable();
```

## Features

### SQL Query Builder
- CREATE SELECT queries
- WHERE conditions
- JOIN operations (INNER, LEFT, RIGHT)
- ORDER BY sorting
- GROUP BY grouping
- HAVING filtering
- LIMIT clause
- Parameter binding support
- DISTINCT queries

### Migration Tool
- Multiple database system support (MySQL, MongoDB, SQL Server)
- Support for all common data types
- Automatic ID/Primary Key generation
- Foreign Key relationships
- Unique constraints
- NOT NULL constraints
- DEFAULT values
- CHECK constraints

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Furkan Ünsal](https://github.com/FurkiFor)
- [All Contributors](../../contributors)
