<?php
namespace Furkifor\SqlDumper;

interface SqlDumperClassInterface {

    /**
     * Select columns to be included in the query.
     * Belirli sütunları sorguya dahil etmek için seçer.
     * 
     * @param string $select
     * @return self
     */
    public function select(string $select = '*');

    /**
     * Add an ORDER BY clause to the query.
     * Sorguya ORDER BY ifadesi ekler.
     * 
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC');

    /**
     * Add a WHERE clause to the query.
     * Sorguya WHERE ifadesi ekler.
     * 
     * @param string $condition
     * @param array $params
     * @param string $operator
     * @return self
     */
    public function where(string $condition, array $params = [], string $operator = 'AND');

    /**
     * Add a JOIN clause to the query.
     * Sorguya JOIN ifadesi ekler.
     * 
     * @param string $type
     * @param string $table
     * @param string $on
     * @return self
     */
    public function join(string $type, string $table, string $on);

    /**
     * Set a LIMIT clause for the query.
     * Sorguya LIMIT ifadesi ekler.
     * 
     * @param int $count
     * @return self
     */
    public function limit(int $count);

    /**
     * Add a GROUP BY clause to the query.
     * Sorguya GROUP BY ifadesi ekler.
     * 
     * @param string $column
     * @return self
     */
    public function groupBy(string $column);

    /**
     * Add a HAVING clause to the query.
     * Sorguya HAVING ifadesi ekler.
     * 
     * @param string $condition
     * @return self
     */
    public function having(string $condition);

    /**
     * Use DISTINCT to avoid duplicate results.
     * Aynı sonuçları önlemek için DISTINCT kullanır.
     * 
     * @return self
     */
    public function distinct();

    /**
     * Build and return the SQL query as a string.
     * SQL sorgusunu oluşturur ve bir dize olarak döner.
     * 
     * @return string
     */
    public function get(): string;

    /**
     * Get the first result from the query.
     * Sorgudan ilk sonucu döner.
     * 
     * @return string
     */
    public function first(): string;

    /**
     * Reset the query builder to its default state.
     * Sorgu oluşturucuyu varsayılan duruma sıfırlar.
     * 
     * @return void
     */
    public function reset(): void;

    /**
     * Execute the built SQL query.
     * Oluşturulan SQL sorgusunu çalıştırır.
     * 
     * @return mixed
     */
    public function execute();
}
