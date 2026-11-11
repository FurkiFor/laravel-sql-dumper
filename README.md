# Laravel SQL Dumper — see the raw SQL of any Eloquent query

[![Latest Version on Packagist](https://img.shields.io/packagist/v/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![Tests](https://github.com/FurkiFor/laravel-sql-dumper/actions/workflows/run-tests.yml/badge.svg)](https://github.com/FurkiFor/laravel-sql-dumper/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![PHP Version](https://img.shields.io/packagist/php-v/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![License](https://img.shields.io/packagist/l/furkifor/sql_dumper.svg?style=flat-square)](LICENSE.md)

**Laravel SQL Dumper** shows you the real SQL behind your code: get the raw SQL of an Eloquent or query builder
query with the bindings filled in, log every executed query, find slow queries and spot N+1 problems.
It also ships a standalone, Eloquent-style **PHP SQL query builder** and a small **migration (CREATE TABLE) builder**
that work without any framework.

```php
User::where('age', '>', 18)->where('role', 'admin')->rawSql();
// select * from "users" where "age" > 18 and "role" = 'admin'
```

🇹🇷 [Türkçe dokümantasyon](README.tr.md)

## Table of contents

- [Why Laravel SQL Dumper?](#why-laravel-sql-dumper)
- [Installation](#installation)
- [Get the raw SQL of an Eloquent query](#get-the-raw-sql-of-an-eloquent-query)
- [Log executed queries and detect N+1](#log-executed-queries-and-detect-n1)
- [Write every query to the Laravel log](#write-every-query-to-the-laravel-log)
- [Standalone PHP SQL query builder](#standalone-php-sql-query-builder)
- [Migration builder (CREATE TABLE)](#migration-builder-create-table)
- [FAQ](#faq)
- [Testing](#testing)

## Why Laravel SQL Dumper?

| You want to… | Use |
|---|---|
| See an Eloquent query as SQL with real values instead of `?` | `->rawSql()` / `->dumpSql()` |
| Know which queries a request or a block of code ran | `QueryLog::start()` |
| Find N+1 queries and slow queries | `$log->duplicates()` / `$log->slow(100)` |
| Log all SQL in local development | `SQL_DUMPER_LOG=true` |
| Fill bindings into any SQL string | `SqlDumper::interpolate($sql, $bindings)` |
| Build SQL in plain PHP without Laravel | `SqlDumper::table('users')` |
| Generate `CREATE TABLE` SQL for MySQL, PostgreSQL, SQLite, SQL Server | `MigrateClass` |

- Zero runtime dependencies; Laravel is optional
- Global scopes, booleans, dates, enums and quotes are handled when values are written into the SQL
- Covered by tests that run against a real Laravel application and a real SQLite database

## Installation

```bash
composer require furkifor/sql_dumper
```

**Requirements:** PHP 8.2+. The Laravel features work on Laravel 11, 12 and 13.

In Laravel the service provider is discovered automatically. Add `--dev` to the command if you only need it while
developing.

## Get the raw SQL of an Eloquent query

`toSql()` gives you question marks. `rawSql()` gives you the query you can paste into your database client:

```php
$query = User::where('age', '>', 18)->where('name', "O'Brien");

$query->toSql();   // select * from "users" where "age" > ? and "name" = ?
$query->rawSql();  // select * from "users" where "age" > 18 and "name" = 'O''Brien'
```

It works on Eloquent builders (global scopes included), relations and `DB::table()`:

```php
DB::table('orders')->whereIn('status', ['paid', 'shipped'])->rawSql();
$user->posts()->where('published', true)->rawSql();
```

Print the SQL in the middle of a chain without breaking it:

```php
$users = User::where('active', true)
    ->dumpSql()          // prints the raw SQL
    ->orderBy('name')
    ->get();
```

Have a SQL string and bindings from somewhere else (a log line, an exception, another library)?

```php
use Furkifor\SqlDumper\SqlDumper;

SqlDumper::interpolate('select * from users where id = ? and role = :role', [5, 'role' => 'admin']);
// select * from users where id = 5 and role = 'admin'
```

## Log executed queries and detect N+1

`QueryLog` records every query that runs after you start it:

```php
use Furkifor\SqlDumper\QueryLog;

$log = QueryLog::start();

$posts = Post::all();
foreach ($posts as $post) {
    $post->author->name;        // N+1
}

$log->count();        // 11
$log->totalTime();    // 8.42 (milliseconds)
$log->toRawSql();     // ['select * from "posts"', 'select * from "users" where "id" = 1 limit 1', ...]
$log->duplicates();   // ['select * from "users" where "id" = ? limit 1' => 10]   <- N+1
$log->slow(100);      // queries that took 100 ms or more, slowest first
$log->dump();
```

`dump()` prints a report:

```text
#1 [0.31 ms] select * from "posts"
#2 [0.12 ms] select * from "users" where "id" = 1 limit 1
...
11 queries, 8.42 ms
repeated 10x: select * from "users" where "id" = ? limit 1
```

Useful in a test to keep a page from regressing:

```php
$log = QueryLog::start();
$this->get('/dashboard');
$this->assertLessThanOrEqual(10, $log->count());
$this->assertSame([], $log->duplicates());
```

`app(QueryLog::class)` gives you a shared log for the whole request. Outside Laravel, call
`$log->record($sql, $bindings, $timeMs)` yourself.

## Write every query to the Laravel log

Set this in `.env` on your development machine:

```dotenv
SQL_DUMPER_LOG=true
# optional: only queries slower than 50 ms, and a dedicated log channel
SQL_DUMPER_SLOW_MS=50
SQL_DUMPER_CHANNEL=daily
```

Each query is written at `debug` level with its bindings filled in, its time and its connection name.
To change the defaults in a file, publish the config:

```bash
php artisan vendor:publish --tag=sql-dumper-config
```

Leave query logging off in production: raw SQL contains your users' data.

## Standalone PHP SQL query builder

No Laravel needed. Build a query, read the SQL, optionally run it through PDO.

```php
use Furkifor\SqlDumper\SqlDumper;

$query = SqlDumper::table('users')
    ->select('name', 'email')
    ->where('age', '>', 18)
    ->where('role', 'admin')
    ->latest()
    ->limit(10);

$query->get();          // SELECT name, email FROM users WHERE age > ? AND role = ? ORDER BY created_at DESC LIMIT 10
$query->getBindings();  // [18, 'admin']
$query->toRawSql();     // SELECT name, email FROM users WHERE age > 18 AND role = 'admin' ORDER BY created_at DESC LIMIT 10
```

### Where conditions

```php
->where('age', 18)                               // age = ?
->where('age', '>=', 18)                         // age >= ?
->where('deleted_at', null)                      // deleted_at IS NULL
->where('age > ? AND city = ?', [18, 'Izmir'])   // raw condition with parameters
->orWhere('role', 'admin')
->whereIn('id', [1, 2, 3])
->whereNotIn('status', ['banned'])
->whereNull('deleted_at')
->whereNotNull('email')
->whereBetween('age', [18, 30])
->whereLike('name', 'fur%')
->whereColumn('updated_at', '>', 'created_at')
->where('expires_at', '<', SqlDumper::raw('NOW()'))

// Grouped conditions: (role = ? OR role = ?)
->where(function ($q) {
    $q->where('role', 'admin')->orWhere('role', 'editor');
})

// Add a condition only when a value is present
->when($role, fn ($q, $role) => $q->where('role', $role))
```

### Subqueries

```php
$bigSpenders = SqlDumper::table('orders')->select('user_id')->where('total', '>', 100);

SqlDumper::table('users')
    ->whereIn('id', $bigSpenders)
    ->whereNotExists(SqlDumper::table('bans')->select('1')->whereColumn('bans.user_id', 'users.id'))
    ->toRawSql();
// SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE total > 100)
//   AND NOT EXISTS (SELECT 1 FROM bans WHERE bans.user_id = users.id)
```

### Joins, grouping, paging, aggregates

```php
SqlDumper::table('users')
    ->select('users.name', 'COUNT(*) AS total')
    ->leftJoin('orders', 'orders.user_id = users.id')     // innerJoin(), rightJoin(), join('CROSS', ...)
    ->groupBy('users.name')
    ->having('COUNT(*) > ?', [2])
    ->orderByDesc('total')
    ->forPage(2, 10)                                       // LIMIT 10 OFFSET 10
    ->get();

SqlDumper::table('orders')->where('status', 'paid')->count()->toRawSql();
// SELECT COUNT(*) AS aggregate FROM orders WHERE status = 'paid'
// also: sum('total'), avg('total'), min('total'), max('total')

SqlDumper::table('users')->distinct()->select('country')->get();   // SELECT DISTINCT country FROM users
SqlDumper::table('users')->selectRaw('price * ? AS gross', [1.2]);
```

### Insert, update, delete

```php
SqlDumper::table('users')->insert(['name' => 'Furkan', 'age' => 30])->toRawSql();
// INSERT INTO users (name, age) VALUES ('Furkan', 30)

SqlDumper::table('users')->insert([
    ['name' => 'A', 'age' => 20],
    ['name' => 'B', 'age' => 25],
])->get();
// INSERT INTO users (name, age) VALUES (?, ?), (?, ?)

SqlDumper::table('users')->where('id', 5)
    ->update(['name' => 'New', 'updated_at' => SqlDumper::raw('NOW()')])
    ->toRawSql();
// UPDATE users SET name = 'New', updated_at = NOW() WHERE id = 5

SqlDumper::table('products')->where('id', 9)->decrement('stock')->toRawSql();
// UPDATE products SET stock = stock - 1 WHERE id = 9

SqlDumper::table('users')->where('age', '<', 18)->delete()->toRawSql();
// DELETE FROM users WHERE age < 18
```

### Readable output for long queries

```php
echo $query->toPrettySql();    // or ->dump(true)
```

```sql
SELECT users.name, COUNT(*) AS total
FROM users
LEFT JOIN orders ON orders.user_id = users.id
WHERE users.active = 1
  AND users.role = 'admin'
GROUP BY users.name
HAVING COUNT(*) > 2
ORDER BY total DESC
LIMIT 10
```

### Run the query with PDO

```php
$pdo = new PDO('mysql:host=localhost;dbname=app', 'user', 'secret');

$rows     = SqlDumper::table('users', $pdo)->where('age', '>', 18)->execute();        // array of rows
$affected = SqlDumper::table('users', $pdo)->where('id', 5)->delete()->execute();     // affected row count
```

Values are sent as bound parameters. Inside Laravel `execute()` uses the default database connection when you do
not pass one.

### Security

- Values are always bound as parameters when a query is executed.
- Table names, column names and raw conditions are written into the SQL as given. Never build them from user input.
- `rawSql()`, `toRawSql()`, `dump()` and `interpolate()` produce SQL for reading, debugging and logging. Execute
  queries with `execute()` or with `get()` + `getBindings()`.

## Migration builder (CREATE TABLE)

Generate `CREATE TABLE` statements for MySQL / MariaDB, PostgreSQL, SQLite and SQL Server:

```php
use Furkifor\SqlDumper\MigrateClass;

$table = new MigrateClass('mysql');           // mysql, pgsql, sqlite or sqlsrv

echo $table->name('posts')->ifNotExists()
    ->string('title')->notNull()
    ->text('content')
    ->int('user_id')->notNull()
    ->foreignKey('user_id', 'users', 'id', 'CASCADE')
    ->boolean('is_published')->default(false)
    ->decimal('price', 10, 2)->default(0)
    ->timestamps()
    ->check('price >= 0')
    ->createTable();
```

```sql
CREATE TABLE IF NOT EXISTS posts (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT,
user_id INT NOT NULL, is_published TINYINT(1) DEFAULT 0, price DECIMAL(10, 2) DEFAULT 0, created_at DATETIME NULL,
updated_at DATETIME NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CHECK (price >= 0))
```

Without a connection `createTable()` only returns the SQL. Pass a PDO connection to run it too:

```php
$table = new MigrateClass('sqlite', $pdo);
$table->name('users')->string('email')->unique()->notNull()->createTable();
$table->dropTable('users');                   // DROP TABLE IF EXISTS users
```

| Columns | Modifiers (apply to the last column) | Table |
|---|---|---|
| `id` `string` `int` `bigInt` `decimal` `float` `boolean` `date` `datetime` `text` `json` `timestamps` | `notNull` `nullable` `unique` `default` `defaultRaw` | `foreignKey` `check` `ifNotExists` `createTable` `toSql` `dropTable` |

`name('table')` adds an auto increment `id` primary key; `name('table', false)` skips it. `default()` quotes strings
and keeps numbers, booleans and expressions such as `CURRENT_TIMESTAMP` or `NOW()` as they are.

## FAQ

### How do I see the raw SQL query in Laravel?

Install this package and call `->rawSql()` on any Eloquent or query builder instance. Unlike `->toSql()`, the result
has the bindings filled in, so you can copy it straight into MySQL, PostgreSQL or SQLite.

### How do I get a Laravel query with bindings instead of question marks?

`$query->rawSql()` for a query you are building, or `SqlDumper::interpolate($sql, $bindings)` for a SQL string and a
bindings array you already have.

### How is this different from Laravel's `toRawSql()` and `DB::enableQueryLog()`?

Laravel has `toRawSql()` built in since 10.15, and this package does not replace it. It adds what is missing around it:
a query log with raw SQL, timings, slow-query filtering and repeated-statement (N+1) detection, env-based SQL logging,
`dumpSql()` that keeps the chain going, a pretty printer, and an interpolator and query builder that work outside
Laravel.

### How do I find N+1 queries in Laravel?

Wrap the code with `QueryLog::start()` and look at `$log->duplicates()`. A `SELECT` that repeats many times with
different bindings is an N+1 query; fix it with eager loading (`with()`).

### How do I log all SQL queries in Laravel?

Set `SQL_DUMPER_LOG=true` in `.env`. Every query is written to the Laravel log with real values and execution time.
Add `SQL_DUMPER_SLOW_MS=100` to log slow queries only.

### Can I use it without Laravel?

Yes. `SqlDumper`, `QueryLog::record()`, `SqlDumper::interpolate()` and `MigrateClass` have no dependencies and work
in any PHP 8.2+ project with or without PDO.

### Is it safe to use in production?

The query builder executes with bound parameters and is safe to use. The dumping and logging features are meant for
development, because raw SQL contains real data.

## Testing

```bash
composer test       # PHPUnit
composer analyse    # PHPStan
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Furkan Ünsal](https://github.com/FurkiFor)
- [All Contributors](../../contributors)
