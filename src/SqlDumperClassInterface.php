<?php

namespace Furkifor\SqlDumper;

interface SqlDumperClassInterface
{
    /**
     * Select columns to be included in the query.
     * Belirli sütunları sorguya dahil etmek için seçer.
     *
     * @param string|string[] ...$columns
     * @return self
     */
    public function select(...$columns);

    /**
     * Add an ORDER BY clause to the query.
     * Sorguya ORDER BY ifadesi ekler.
     *
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC');

    /**
     * Add a WHERE clause to the query.
     * Sorguya WHERE ifadesi ekler.
     *
     * where('age', 18), where('age', '>', 18) or where('age > ?', [18]).
     *
     * @param string|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @return self
     */
    public function where(string|\Closure $column, mixed $operator = [], mixed $value = null, string $boolean = 'AND');

    /**
     * Add a JOIN clause to the query.
     * Sorguya JOIN ifadesi ekler.
     *
     * @return self
     */
    public function join(string $type, string $table, string $on = '', array $params = []);

    /**
     * Set a LIMIT clause for the query.
     * Sorguya LIMIT ifadesi ekler.
     *
     * @return self
     */
    public function limit(int $count);

    /**
     * Add GROUP BY columns to the query.
     * Sorguya GROUP BY ifadesi ekler.
     *
     * @return self
     */
    public function groupBy(string ...$columns);

    /**
     * Add a HAVING clause to the query.
     * Sorguya HAVING ifadesi ekler.
     *
     * @return self
     */
    public function having(string $condition, array $params = []);

    /**
     * Use DISTINCT to avoid duplicate results.
     * Aynı sonuçları önlemek için DISTINCT kullanır.
     *
     * @return self
     */
    public function distinct();

    /**
     * Build and return the SQL query (with ? placeholders) as a string.
     * SQL sorgusunu oluşturur ve bir dize olarak döner.
     */
    public function get(): string;

    /**
     * The same query limited to a single row.
     * Sorguyu tek satırla sınırlar.
     */
    public function first(): string;

    /**
     * Bound parameters in the order they appear in the SQL.
     * Sorguya bağlanan parametreleri döner.
     */
    public function getBindings(): array;

    /**
     * The SQL with the parameters written in place (for debugging).
     * Parametreleri yerine yazılmış SQL çıktısını döner.
     */
    public function toRawSql(): string;

    /**
     * Reset the query builder to its default state.
     * Sorgu oluşturucuyu varsayılan duruma sıfırlar.
     */
    public function reset(): void;

    /**
     * Execute the built SQL query.
     * Oluşturulan SQL sorgusunu çalıştırır.
     *
     * @return array|int rows for SELECT, affected row count otherwise
     */
    public function execute(?\PDO $pdo = null): array|int;
}
