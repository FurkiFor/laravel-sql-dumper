# Changelog

All notable changes to `sql-dumper` will be documented in this file.

## 2.0.0 - unreleased

Requires PHP 8.2+. Laravel features support Laravel 11, 12 and 13.

### Laravel
- Auto-discovered service provider
- `rawSql()` and `dumpSql()` on Eloquent builders, relations and `DB::table()` (global scopes applied)
- `QueryLog`: records executed queries as raw SQL with timings; `duplicates()` for N+1 detection, `slow()`,
  `totalTime()`, `report()` / `dump()`
- `SQL_DUMPER_LOG`, `SQL_DUMPER_SLOW_MS`, `SQL_DUMPER_CHANNEL` to write executed queries to the Laravel log
- `SqlDumper::execute()` uses the default Laravel connection when no PDO is given

### Query builder
- New `Furkifor\SqlDumper\SqlDumper` class (`SqlDumperClass` still works) with a `SqlDumper::table()` shortcut
- `where('age', 18)`, `where('age', '>', 18)`, `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`,
  `whereBetween`, `whereLike`, `whereColumn`, `whereExists`, `whereNotExists`, nested groups with a closure, `when()`
- Subqueries in `whereIn()` / `whereExists()`
- `SqlDumper::raw()` expressions in conditions, inserts and updates
- `toRawSql()`, `toPrettySql()`, `dump()`, `dd()` and static `SqlDumper::interpolate()`
- `count()`, `sum()`, `avg()`, `min()`, `max()`
- `insert()` (single and bulk), `update()`, `increment()`, `decrement()`, `delete()`
- `getBindings()`, `toSql()`, `selectRaw()`, `addSelect()`, `offset()`, `forPage()`, `orderByDesc()`, `latest()`,
  `oldest()`, `innerJoin()`, `leftJoin()`, `rightJoin()`
- `execute()` now works: pass a PDO connection; returns rows for SELECT and the affected row count otherwise
- Fixed: the class did not parse because of placeholder code in `execute()`
- Fixed: `WHERE` started with `AND` / `OR`
- Fixed: `distinct()` was ignored when called after `select()`
- Changed: `orderBy()` and `groupBy()` add to the existing list instead of replacing it
- Changed: `first()` no longer modifies the builder

### Migration tool
- Moved to the `Furkifor\SqlDumper` namespace so it can be autoloaded
- No `config.ini` or database connection needed: `toSql()` / `createTable()` return the SQL,
  and run it only when a PDO connection is passed to the constructor
- Added `unique()`, `notNull()`, `nullable()`, `decimal()`, `bigInt()`, `float()`, `date()`, `json()`,
  `timestamps()`, `defaultRaw()`, `ifNotExists()`, `dropTable()`
- MySQL, SQLite, PostgreSQL and SQL Server column types
- `foreignKey()` and `check()` produce table constraints; `foreignKey()` no longer duplicates an existing column
- `default()` quotes string values
- Fixed: columns of a previous table leaked into the next `name()` call

### Tooling
- PHPUnit test suite (unit tests plus tests against a real Laravel application via Testbench)
- PHPStan static analysis
- CI on PHP 8.2, 8.3 and 8.4

## 1.0.0

- initial release
