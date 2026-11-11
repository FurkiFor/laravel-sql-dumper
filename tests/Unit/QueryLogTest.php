<?php

namespace Furkifor\SqlDumper\Tests\Unit;

use Furkifor\SqlDumper\QueryLog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QueryLogTest extends TestCase
{
    public function testRecordsQueriesWithoutLaravel(): void
    {
        $log = new QueryLog();
        $log->record('SELECT * FROM users WHERE id = ?', [1], 1.5)
            ->record('SELECT * FROM posts WHERE user_id = ?', [1], 120.0, 'mysql')
            ->record('SELECT * FROM posts WHERE user_id = ?', [2], 250.25, 'mysql');

        $this->assertCount(3, $log);
        $this->assertSame(371.75, $log->totalTime());
        $this->assertSame(
            ['SELECT * FROM users WHERE id = 1', 'SELECT * FROM posts WHERE user_id = 1', 'SELECT * FROM posts WHERE user_id = 2'],
            $log->toRawSql()
        );
        $this->assertSame(['SELECT * FROM posts WHERE user_id = ?' => 2], $log->duplicates());
        $this->assertSame([250.25, 120.0], array_column($log->slow(100), 'time'));
        $this->assertSame([], $log->slow(1000));
        $this->assertSame(
            implode(PHP_EOL, [
                '#1 [1.50 ms] SELECT * FROM users WHERE id = 1',
                '#2 [120.00 ms] SELECT * FROM posts WHERE user_id = 1',
                '#3 [250.25 ms] SELECT * FROM posts WHERE user_id = 2',
                '3 queries, 371.75 ms',
                'repeated 2x: SELECT * FROM posts WHERE user_id = ?',
            ]),
            $log->report()
        );
    }

    public function testStopResumeAndClear(): void
    {
        $log = new QueryLog();
        $log->record('SELECT 1')->stop()->record('SELECT 2')->resume()->record('SELECT 3');

        $this->assertSame(['SELECT 1', 'SELECT 3'], $log->toRawSql());
        $this->assertCount(0, $log->clear());
        $this->assertSame('0 queries, 0.00 ms', $log->report());
    }

    public function testListensToAnyObjectWithAListenMethod(): void
    {
        $source = new class () {
            /** @var callable|null */
            public $listener;

            public function listen(callable $listener): void
            {
                $this->listener = $listener;
            }
        };

        $log = QueryLog::start($source);
        ($source->listener)((object) ['sql' => 'SELECT ?', 'bindings' => ['a'], 'time' => 2, 'connectionName' => 'x']);

        $this->assertSame(
            [['sql' => 'SELECT ?', 'bindings' => ['a'], 'raw' => "SELECT 'a'", 'time' => 2.0, 'connection' => 'x']],
            $log->all()
        );
    }

    public function testRejectsASourceWithoutListen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QueryLog::start(new \stdClass());
    }
}
