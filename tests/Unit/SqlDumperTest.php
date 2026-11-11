<?php

namespace Furkifor\SqlDumper\Tests\Unit;

use Furkifor\SqlDumper\SqlDumper;
use Furkifor\SqlDumper\SqlDumperClass;
use InvalidArgumentException;
use LogicException;
use PDO;
use PHPUnit\Framework\TestCase;

class SqlDumperTest extends TestCase
{
    public function testSimpleSelect(): void
    {
        $this->assertSame('SELECT * FROM users', (new SqlDumper('users'))->select('*')->get());
        $this->assertSame('SELECT * FROM users', SqlDumper::table('users')->get());
        $this->assertSame('SELECT * FROM users', (string) SqlDumper::table('users'));
    }

    public function testSelectAcceptsStringListAndArray(): void
    {
        $expected = 'SELECT name, email FROM users';

        $this->assertSame($expected, SqlDumper::table('users')->select('name, email')->get());
        $this->assertSame($expected, SqlDumper::table('users')->select('name', 'email')->get());
        $this->assertSame($expected, SqlDumper::table('users')->select(['name', 'email'])->get());
        $this->assertSame($expected, SqlDumper::table('users')->select('name')->addSelect('email')->get());
    }

    public function testDistinctWorksInAnyOrder(): void
    {
        $expected = 'SELECT DISTINCT country FROM users';

        $this->assertSame($expected, SqlDumper::table('users')->distinct()->select('country')->get());
        $this->assertSame($expected, SqlDumper::table('users')->select('country')->distinct()->get());
    }

    public function testReadmeConditionExample(): void
    {
        $query = SqlDumper::table('users')
            ->select('name, email')
            ->where('age > ?', [18])
            ->orderBy('name', 'DESC')
            ->limit(10);

        $this->assertSame('SELECT name, email FROM users WHERE age > ? ORDER BY name DESC LIMIT 10', $query->get());
        $this->assertSame([18], $query->getBindings());
        $this->assertSame('SELECT name, email FROM users WHERE age > 18 ORDER BY name DESC LIMIT 10', $query->toRawSql());
    }

    public function testWhereForms(): void
    {
        $query = SqlDumper::table('users')
            ->where('role', 'admin')
            ->where('age', '>=', 18)
            ->where('active = 1')
            ->where('deleted_at', null)
            ->where('email', '!=', null)
            ->orWhere('id', 1)
            ->where('score > ?', [50], 'OR');

        $this->assertSame(
            'SELECT * FROM users WHERE role = ? AND age >= ? AND active = 1 AND deleted_at IS NULL'
            . ' AND email IS NOT NULL OR id = ? OR score > ?',
            $query->get()
        );
        $this->assertSame(['admin', 18, 1, 50], $query->getBindings());
    }

    public function testWhereHelpers(): void
    {
        $query = SqlDumper::table('users')
            ->whereIn('id', [1, 2, 3])
            ->whereNotIn('role', ['banned'])
            ->whereBetween('age', [18, 30])
            ->whereLike('name', 'fur%')
            ->whereNotNull('email');

        $this->assertSame(
            'SELECT * FROM users WHERE id IN (?, ?, ?) AND role NOT IN (?) AND age BETWEEN ? AND ?'
            . ' AND name LIKE ? AND email IS NOT NULL',
            $query->get()
        );
        $this->assertSame([1, 2, 3, 'banned', 18, 30, 'fur%'], $query->getBindings());
    }

    public function testEmptyWhereInMatchesNothing(): void
    {
        $this->assertSame('SELECT * FROM users WHERE 0 = 1', SqlDumper::table('users')->whereIn('id', [])->get());
        $this->assertSame('SELECT * FROM users WHERE 1 = 1', SqlDumper::table('users')->whereNotIn('id', [])->get());
    }

    public function testNestedWhere(): void
    {
        $query = SqlDumper::table('users')
            ->where('active', true)
            ->where(function (SqlDumper $q) {
                $q->where('role', 'admin')->orWhere('role', 'editor');
            });

        $this->assertSame('SELECT * FROM users WHERE active = ? AND (role = ? OR role = ?)', $query->get());
        $this->assertSame([true, 'admin', 'editor'], $query->getBindings());
        $this->assertSame(
            "SELECT * FROM users WHERE active = 1 AND (role = 'admin' OR role = 'editor')",
            $query->toRawSql()
        );
    }

    public function testWhen(): void
    {
        $build = function (?string $role): string {
            return SqlDumper::table('users')
                ->when($role, function (SqlDumper $q, string $role) {
                    $q->where('role', $role);
                })
                ->get();
        };

        $this->assertSame('SELECT * FROM users WHERE role = ?', $build('admin'));
        $this->assertSame('SELECT * FROM users', $build(null));
    }

    public function testJoins(): void
    {
        $query = SqlDumper::table('users')
            ->select('users.*, roles.name as role_name')
            ->join('inner', 'roles', 'users.role_id = roles.id')
            ->leftJoin('teams', 'teams.id = users.team_id AND teams.kind = ?', ['core'])
            ->where('users.active = ?', [1]);

        $this->assertSame(
            'SELECT users.*, roles.name as role_name FROM users INNER JOIN roles ON users.role_id = roles.id'
            . ' LEFT JOIN teams ON teams.id = users.team_id AND teams.kind = ? WHERE users.active = ?',
            $query->get()
        );
        $this->assertSame(['core', 1], $query->getBindings());
    }

    public function testGroupHavingOrderLimitOffset(): void
    {
        $query = SqlDumper::table('users')
            ->select('country, COUNT(*) as user_count')
            ->groupBy('country')
            ->having('COUNT(*) > ?', [100])
            ->orderBy('user_count', 'desc')
            ->orderBy('country')
            ->forPage(3, 20);

        $this->assertSame(
            'SELECT country, COUNT(*) as user_count FROM users GROUP BY country HAVING COUNT(*) > ?'
            . ' ORDER BY user_count DESC, country ASC LIMIT 20 OFFSET 40',
            $query->get()
        );
        $this->assertSame([100], $query->getBindings());
    }

    public function testFirstAndCountDoNotModifyTheBuilder(): void
    {
        $query = SqlDumper::table('users')->where('age', '>', 18)->orderBy('name')->limit(10);

        $this->assertSame('SELECT * FROM users WHERE age > ? ORDER BY name ASC LIMIT 1', $query->first());
        $this->assertSame('SELECT COUNT(*) AS aggregate FROM users WHERE age > ?', $query->count()->get());
        $this->assertSame('SELECT * FROM users WHERE age > ? ORDER BY name ASC LIMIT 10', $query->get());
    }

    public function testAggregates(): void
    {
        $query = SqlDumper::table('orders')->selectRaw('total * ? AS gross', [1.2])->where('status', 'paid')->latest();

        $this->assertSame("SELECT COUNT(*) AS aggregate FROM orders WHERE status = 'paid'", $query->count()->toRawSql());
        $this->assertSame("SELECT SUM(total) AS aggregate FROM orders WHERE status = 'paid'", $query->sum('total')->toRawSql());
        $this->assertSame('SELECT AVG(total) AS aggregate FROM orders WHERE status = ?', (string) $query->avg('total'));
        $this->assertSame('SELECT MIN(total) AS aggregate FROM orders WHERE status = ?', $query->min('total')->get());
        $this->assertSame('SELECT MAX(total) AS aggregate FROM orders WHERE status = ?', $query->max('total')->get());
        $this->assertSame(['paid'], $query->max('total')->getBindings());
        $this->assertSame(
            "SELECT total * 1.2 AS gross FROM orders WHERE status = 'paid' ORDER BY created_at DESC",
            $query->toRawSql()
        );
    }

    public function testSubqueries(): void
    {
        $paid = SqlDumper::table('orders')->select('user_id')->where('total', '>', 100);

        $query = SqlDumper::table('users')
            ->where('active', 1)
            ->whereIn('id', $paid)
            ->whereNotExists(SqlDumper::table('bans')->select('1')->whereColumn('bans.user_id', 'users.id'))
            ->where('age', '>', 18);

        $this->assertSame(
            'SELECT * FROM users WHERE active = ? AND id IN (SELECT user_id FROM orders WHERE total > ?)'
            . ' AND NOT EXISTS (SELECT 1 FROM bans WHERE bans.user_id = users.id) AND age > ?',
            $query->get()
        );
        $this->assertSame([1, 100, 18], $query->getBindings());
    }

    public function testWhereColumnAndRawExpressions(): void
    {
        $query = SqlDumper::table('posts')
            ->whereColumn('updated_at', '>', 'created_at')
            ->where('published_at', '<=', SqlDumper::raw('NOW()'))
            ->oldest('published_at');

        $this->assertSame(
            'SELECT * FROM posts WHERE updated_at > created_at AND published_at <= NOW() ORDER BY published_at ASC',
            $query->get()
        );
        $this->assertSame([], $query->getBindings());
    }

    public function testRawValuesInWritesAndIncrement(): void
    {
        $update = SqlDumper::table('users')->where('id', 3)
            ->update(['name' => 'x', 'updated_at' => SqlDumper::raw('NOW()')]);
        $this->assertSame('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?', $update->get());
        $this->assertSame(['x', 3], $update->getBindings());

        $insert = SqlDumper::table('users')->insert(['name' => 'x', 'created_at' => SqlDumper::raw('NOW()')]);
        $this->assertSame('INSERT INTO users (name, created_at) VALUES (?, NOW())', $insert->get());
        $this->assertSame(['x'], $insert->getBindings());

        $increment = SqlDumper::table('products')->where('id', 9)->increment('stock', 5, ['restocked' => true]);
        $this->assertSame('UPDATE products SET stock = stock + ?, restocked = ? WHERE id = ?', $increment->get());
        $this->assertSame([5, true, 9], $increment->getBindings());

        $this->assertSame(
            'UPDATE products SET stock = stock - 1 WHERE id = 9',
            SqlDumper::table('products')->where('id', 9)->decrement('stock')->toRawSql()
        );
    }

    public function testUpdateWithJoinKeepsBindingOrder(): void
    {
        $query = SqlDumper::table('users')
            ->leftJoin('teams', 'teams.id = users.team_id AND teams.kind = ?', ['core'])
            ->where('users.age', '>', 18)
            ->update(['users.flag' => 'y']);

        $this->assertSame(
            "UPDATE users LEFT JOIN teams ON teams.id = users.team_id AND teams.kind = 'core'"
            . " SET users.flag = 'y' WHERE users.age > 18",
            $query->toRawSql()
        );
    }

    public function testPrettySql(): void
    {
        $query = SqlDumper::table('users')
            ->select('users.name', 'COUNT(*) AS total')
            ->leftJoin('orders', 'orders.user_id = users.id')
            ->where('users.active', 1)
            ->where('users.role', 'admin')
            ->orWhere('users.id', 1)
            ->groupBy('users.name')
            ->having('COUNT(*) > ?', [2])
            ->orderByDesc('total')
            ->forPage(2, 10);

        $expected = implode("\n", [
            'SELECT users.name, COUNT(*) AS total',
            'FROM users',
            'LEFT JOIN orders ON orders.user_id = users.id',
            'WHERE users.active = 1',
            "  AND users.role = 'admin'",
            '  OR users.id = 1',
            'GROUP BY users.name',
            'HAVING COUNT(*) > 2',
            'ORDER BY total DESC',
            'LIMIT 10',
            'OFFSET 10',
        ]);

        $this->assertSame($expected, $query->toPrettySql());
        $this->assertSame(
            "DELETE FROM users\nWHERE age < 18\n  AND active = 0",
            SqlDumper::table('users')->where('age', '<', 18)->where('active', 0)->delete()->toPrettySql()
        );
        $this->assertSame(
            "INSERT INTO users (name)\nVALUES ('a'), ('b')",
            SqlDumper::table('users')->insert([['name' => 'a'], ['name' => 'b']])->toPrettySql()
        );
    }

    public function testInsert(): void
    {
        $query = SqlDumper::table('users')->insert(['name' => "O'Brien", 'age' => 30]);

        $this->assertSame('INSERT INTO users (name, age) VALUES (?, ?)', $query->get());
        $this->assertSame(["O'Brien", 30], $query->getBindings());
        $this->assertSame("INSERT INTO users (name, age) VALUES ('O''Brien', 30)", $query->toRawSql());

        $many = SqlDumper::table('users')->insert([
            ['name' => 'a', 'age' => 1],
            ['name' => 'b', 'age' => 2],
        ]);
        $this->assertSame('INSERT INTO users (name, age) VALUES (?, ?), (?, ?)', $many->get());
        $this->assertSame(['a', 1, 'b', 2], $many->getBindings());
    }

    public function testUpdateAndDelete(): void
    {
        $update = SqlDumper::table('users')->where('id', 5)->update(['name' => 'x', 'deleted_at' => null]);

        $this->assertSame('UPDATE users SET name = ?, deleted_at = ? WHERE id = ?', $update->get());
        $this->assertSame(['x', null, 5], $update->getBindings());
        $this->assertSame("UPDATE users SET name = 'x', deleted_at = NULL WHERE id = 5", $update->toRawSql());

        $delete = SqlDumper::table('users')->where('age', '<', 18)->delete();

        $this->assertSame('DELETE FROM users WHERE age < ?', $delete->get());
        $this->assertSame('DELETE FROM users WHERE age < 18', $delete->toRawSql());
    }

    public function testInterpolateSkipsQuotedTextAndSupportsNamedBindings(): void
    {
        $this->assertSame(
            "SELECT * FROM t WHERE a = 'what?' AND b = 'x' AND c = 2",
            SqlDumper::interpolate("SELECT * FROM t WHERE a = 'what?' AND b = ? AND c = ?", ['x', 2])
        );
        $this->assertSame(
            "SELECT id::text FROM t WHERE a = 'x' AND b = 1.5",
            SqlDumper::interpolate('SELECT id::text FROM t WHERE a = :a AND b = :b', ['a' => 'x', ':b' => 1.5])
        );
        $this->assertSame(
            "SELECT * FROM t WHERE d = '2026-01-02 03:04:05'",
            SqlDumper::interpolate('SELECT * FROM t WHERE d = ?', [new \DateTimeImmutable('2026-01-02 03:04:05')])
        );
        $this->assertSame(
            "select * from t where data ? 'key' and id = 5",
            SqlDumper::interpolate('select * from t where data ?? ? and id = ?', ['key', 5])
        );
    }

    public function testDumpPrintsRawSqlAndReturnsBuilder(): void
    {
        $query = SqlDumper::table('users')->where('id', 7);

        ob_start();
        $returned = $query->dump();
        $output = ob_get_clean();

        $this->assertSame($query, $returned);
        $this->assertSame('SELECT * FROM users WHERE id = 7' . PHP_EOL, $output);
    }

    public function testReset(): void
    {
        $query = SqlDumper::table('users')->select('name')->where('id', 1)->limit(1);
        $query->reset();

        $this->assertSame('SELECT * FROM users', $query->get());
        $this->assertSame([], $query->getBindings());
    }

    public function testLegacyClassStillWorks(): void
    {
        $table = new SqlDumperClass('user');

        $this->assertSame('SELECT * FROM user', $table->select()->get());
    }

    public function testInvalidInputIsRejected(): void
    {
        $cases = [
            function () { SqlDumper::table('users')->orderBy('name', 'sideways'); },
            function () { SqlDumper::table('users')->join('OUTER', 'roles', 'a = b'); },
            function () { SqlDumper::table('users')->limit(0); },
            function () { SqlDumper::table('users')->offset(-1); },
            function () { SqlDumper::table('users')->where('age', '; DROP', 1); },
            function () { SqlDumper::table('users')->where('a = ?', [1], 'XOR'); },
            function () { SqlDumper::table('users')->whereBetween('age', [1]); },
            function () { SqlDumper::table('users')->insert([]); },
            function () { SqlDumper::table('users')->insert([['a' => 1], ['b' => 2]]); },
            function () { SqlDumper::table('users')->update([]); },
            function () { new SqlDumper(''); },
        ];

        foreach ($cases as $i => $case) {
            try {
                $case();
                $this->fail("Case #$i did not throw");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testExecuteWithoutConnectionThrows(): void
    {
        $this->expectException(LogicException::class);

        SqlDumper::table('users')->execute();
    }

    public function testExecuteAgainstSqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not available');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50), age INTEGER)');

        $inserted = SqlDumper::table('users', $pdo)->insert([
            ['name' => 'Ali', 'age' => 17],
            ['name' => "O'Brien", 'age' => 30],
            ['name' => 'Zeynep', 'age' => 41],
        ])->execute();
        $this->assertSame(3, $inserted);

        $rows = SqlDumper::table('users')
            ->select('name')
            ->where('age', '>', 18)
            ->orderByDesc('age')
            ->execute($pdo);
        $this->assertSame([['name' => 'Zeynep'], ['name' => "O'Brien"]], $rows);

        $updated = SqlDumper::table('users')->connection($pdo)->where('name', 'Ali')->update(['age' => 18])->execute();
        $this->assertSame(1, $updated);

        $deleted = SqlDumper::table('users', $pdo)->whereIn('age', [18, 30])->delete()->execute();
        $this->assertSame(2, $deleted);

        $this->assertSame(
            [['aggregate' => 1]],
            array_map(
                static function (array $row): array {
                    return ['aggregate' => (int) $row['aggregate']];
                },
                SqlDumper::table('users', $pdo)->count()->execute()
            )
        );

        $this->assertSame(1, SqlDumper::table('users', $pdo)->where('name', 'Zeynep')->increment('age', 2)->execute());
        $this->assertSame(
            [['aggregate' => 43]],
            array_map(
                static function (array $row): array {
                    return ['aggregate' => (int) $row['aggregate']];
                },
                SqlDumper::table('users', $pdo)->max('age')->execute()
            )
        );
    }
}
