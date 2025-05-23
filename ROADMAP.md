# Roadmap

This document outlines planned future enhancements and features for the SqlDumper package.
The project is actively maintained, and contributions are welcome! If you'd like to contribute, please see our [CONTRIBUTING.md](.github/CONTRIBUTING.md) or open an issue to discuss a new feature.

## SqlDumperClass (Query Builder)

The `SqlDumperClass` provides a fluent interface for building SQL SELECT queries. Future enhancements aim to expand its capabilities and improve its robustness:

*   **CRUD Operations:**
    *   **INSERT Statements:** Add functionality to build `INSERT` queries.
    *   **UPDATE Statements:** Add functionality to build `UPDATE` queries.
    *   **DELETE Statements:** Add functionality to build `DELETE` queries.

*   **Enhanced Querying:**
    *   **Advanced WHERE Clauses:**
        *   Easier `OR WHERE` conditions.
        *   Support for nested conditions (e.g., `WHERE (col1 = ? AND col2 = ?) OR col3 = ?`).
        *   Dedicated methods for `WHERE IN (...)`, `WHERE BETWEEN ...`, `WHERE NULL`, `WHERE NOT NULL`.
    *   **Subqueries:** Enable the use of subqueries within `SELECT`, `FROM`, or `WHERE` clauses.
    *   **OFFSET Clause:** Add an `offset()` method for pagination, complementing the existing `limit()` method.
    *   **Aggregate Function Helpers:** Introduce convenient methods for common aggregate functions with aliases (e.g., `countAs('total')`, `sumAs('amount', 'total_amount')`).
    *   **Raw SQL Expressions:** Provide a way to inject raw SQL snippets for complex scenarios not covered by the builder.

*   **Execution and Utility:**
    *   **Improved `execute()` Method:**
        *   Refactor `execute()` to accept an existing database connection (e.g., PDO, mysqli object) instead of instantiating one internally.
        *   Allow specifying the fetch mode or returning different types of results (e.g., single row, specific column, success boolean for write operations).
    *   **Schema Introspection:** Potentially add utility methods to retrieve basic schema information like listing tables or columns (could also be a separate companion utility).
    *   **Fix WHERE Clause Construction:** Correct the syntactic issue in the `get()` method where `WHERE` clauses are assembled. Currently, it can incorrectly prepend `AND` or `OR` to the first condition (e.g., `SELECT * FROM users WHERE AND age > ?`). The logic needs to ensure that `WHERE` is followed directly by the first condition, and subsequent conditions are correctly appended with `AND` or `OR`.
    *   **Refined Parameter Handling:** Further review and solidify parameter handling in `where()` clauses to ensure security and prevent SQL injection vulnerabilities, promoting the use of prepared statements for all parameters.

## MigrateClass (Migration Tool)

The `MigrateClass` assists in database table creation. The goal is to evolve this into a more comprehensive migration and schema management tool.

*   **Broader Database Support:**
    *   **Full Implementation for MongoDB:** Complete the connection and schema generation logic for MongoDB.
    *   **Full Implementation for SQL Server:** Complete the connection and schema generation logic for SQL Server.
    *   **Support for PostgreSQL and SQLite:** Extend compatibility to other popular database systems.

*   **Extended Data Types and Constraints:**
    *   **Additional Data Types:** Introduce support for `DATE`, `TIME`, `TIMESTAMP` (with precision), `FLOAT`, `DOUBLE`, `DECIMAL` (with precision, currently uses `VARCHAR`), `BLOB`, `ENUM`, `SET`, `JSON`.
    *   **More Column Constraints/Modifiers:**
        *   `UNSIGNED` for numeric types.
        *   `ON UPDATE` / `ON DELETE` actions for foreign keys (e.g., `CASCADE`, `SET NULL`).
        *   Ability to set `PRIMARY KEY` on specific columns or composite keys, not just the default `id`.
        *   Full support for `INDEX` creation (normal, unique, fulltext).
        *   `COMMENT` support for tables and columns.
        *   `CHARACTER SET` and `COLLATION` options per column/table.
    *   **Fluent Constraint Methods:** Introduce dedicated chainable methods for common constraints like `->unique()`, `->nullable()`, `->default(<value>)` for a more expressive API, aligning with initial README examples.

*   **Schema Manipulation:**
    *   **Table Alteration (`ALTER TABLE`):**
        *   Add new columns to existing tables.
        *   Drop columns from existing tables.
        *   Modify existing column definitions (type, constraints, default value).
        *   Rename columns.
        *   Rename tables.
        *   Add/drop constraints on existing tables.
    *   **Dropping Tables:** Implement a `dropTable()` or `dropTableIfExists()` method.
    *   **Dropping Foreign Keys/Indexes:** Methods to specifically remove these.

*   **Full Migration System:**
    *   **Migration Tracking:** Implement a system to track executed migrations (e.g., using a dedicated database table).
    *   **Up and Down Migrations:** Define migrations with `up()` (to apply changes) and `down()` (to revert changes) methods.
    *   **CLI Tool:** Develop a basic command-line interface (CLI) tool for managing migrations (e.g., `migrate`, `rollback`, `status`).
    *   **Migration Generation:** Commands to generate new blank migration files.

*   **Data Seeding:**
    *   Provide a mechanism to define and run seeders for populating tables with initial or test data.

*   **Improved Configuration:**
    *   Move away from `config.ini` for database connections in `MigrateClass` towards a more flexible configuration approach, perhaps by passing connection parameters or a connection object directly, similar to the proposed changes for `SqlDumperClass::execute()`.

---

This roadmap is subject to change based on community feedback and project priorities.
