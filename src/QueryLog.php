<?php

namespace Furkifor\SqlDumper;

use Countable;
use InvalidArgumentException;
use LogicException;

/**
 * Collects executed queries with their bindings filled in, so you can see what a
 * piece of code really sent to the database, how long it took and which queries repeat (N+1).
 *
 * In Laravel:
 *
 *   $log = QueryLog::start();
 *   // ... code under inspection ...
 *   $log->dump();
 *
 * Without Laravel, call record() yourself after running a query.
 */
class QueryLog implements Countable
{
    /** @var array<int, array{sql: string, bindings: array<int|string, mixed>, raw: string, time: float, connection: ?string}> */
    private array $queries = [];

    private bool $recording = true;

    /**
     * Create a log and start listening.
     *
     * @param object|null $source a Laravel connection or DatabaseManager (anything with a listen() method);
     *                            null uses Laravel's DB facade
     */
    public static function start(?object $source = null): self
    {
        return (new self())->listenTo($source);
    }

    /**
     * Record every query executed on the given Laravel connection / database manager.
     */
    public function listenTo(?object $source = null): self
    {
        $listener = function (object $event): void {
            $bindings = (array) ($event->bindings ?? []);
            $connection = $event->connection ?? null;
            if (is_object($connection) && method_exists($connection, 'prepareBindings')) {
                $bindings = $connection->prepareBindings($bindings);
            }

            $this->record(
                (string) ($event->sql ?? ''),
                $bindings,
                (float) ($event->time ?? 0),
                isset($event->connectionName) ? (string) $event->connectionName : null
            );
        };

        if ($source === null) {
            $facade = '\Illuminate\Support\Facades\DB';
            if (! class_exists($facade)) {
                throw new LogicException('QueryLog::start() without arguments needs Laravel. Use record() instead.');
            }
            $facade::listen($listener);
        } elseif (method_exists($source, 'listen') || method_exists($source, '__call')) {
            // Laravel's DatabaseManager forwards listen() to the default connection through __call.
            $source->listen($listener);
        } else {
            throw new InvalidArgumentException('The query source must have a listen() method');
        }

        return $this;
    }

    /**
     * Add a query to the log.
     *
     * @param array<int|string, mixed> $bindings
     * @param float $timeMs execution time in milliseconds
     */
    public function record(string $sql, array $bindings = [], float $timeMs = 0.0, ?string $connection = null): self
    {
        if ($this->recording) {
            $this->queries[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'raw' => SqlDumper::interpolate($sql, $bindings),
                'time' => $timeMs,
                'connection' => $connection,
            ];
        }

        return $this;
    }

    /**
     * Stop recording. Collected queries are kept.
     */
    public function stop(): self
    {
        $this->recording = false;

        return $this;
    }

    public function resume(): self
    {
        $this->recording = true;

        return $this;
    }

    public function clear(): self
    {
        $this->queries = [];

        return $this;
    }

    /**
     * @return array<int, array{sql: string, bindings: array<int|string, mixed>, raw: string, time: float, connection: ?string}>
     */
    public function all(): array
    {
        return $this->queries;
    }

    /**
     * The executed queries as raw SQL strings.
     *
     * @return string[]
     */
    public function toRawSql(): array
    {
        return array_column($this->queries, 'raw');
    }

    public function count(): int
    {
        return count($this->queries);
    }

    /**
     * Total execution time in milliseconds.
     */
    public function totalTime(): float
    {
        return (float) array_sum(array_column($this->queries, 'time'));
    }

    /**
     * Queries that took at least $thresholdMs milliseconds, slowest first.
     *
     * @return array<int, array{sql: string, bindings: array<int|string, mixed>, raw: string, time: float, connection: ?string}>
     */
    public function slow(float $thresholdMs = 100.0): array
    {
        $slow = array_values(array_filter($this->queries, static function (array $query) use ($thresholdMs): bool {
            return $query['time'] >= $thresholdMs;
        }));

        usort($slow, static function (array $a, array $b): int {
            return $b['time'] <=> $a['time'];
        });

        return $slow;
    }

    /**
     * Statements that ran more than once (same SQL, any bindings), most repeated first.
     * Many repeats of one SELECT is the usual sign of an N+1 problem.
     *
     * @return array<string, int> SQL with placeholders => number of executions
     */
    public function duplicates(): array
    {
        $counts = array_count_values(array_column($this->queries, 'sql'));
        $duplicates = array_filter($counts, static function (int $count): bool {
            return $count > 1;
        });
        arsort($duplicates);

        return $duplicates;
    }

    /**
     * A readable report: every query with its time, then totals and repeated statements.
     */
    public function report(): string
    {
        $lines = [];
        foreach ($this->queries as $i => $query) {
            $lines[] = sprintf('#%d [%.2f ms] %s', $i + 1, $query['time'], $query['raw']);
        }

        $lines[] = sprintf('%d %s, %.2f ms', $this->count(), $this->count() === 1 ? 'query' : 'queries', $this->totalTime());

        foreach ($this->duplicates() as $sql => $count) {
            $lines[] = sprintf('repeated %dx: %s', $count, $sql);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Print the report and keep the log.
     */
    public function dump(): self
    {
        echo $this->report() . PHP_EOL;

        return $this;
    }
}
