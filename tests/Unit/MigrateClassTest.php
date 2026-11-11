<?php

namespace Furkifor\SqlDumper\Tests\Unit;

use Furkifor\SqlDumper\MigrateClass;
use InvalidArgumentException;
use LogicException;
use PDO;
use PHPUnit\Framework\TestCase;

class MigrateClassTest extends TestCase
{
    public function testReadmeUsersTable(): void
    {
        $sql = (new MigrateClass('mysql'))->name('users')
            ->string('username', 255)->unique()->notnull()
            ->string('email', 255)->unique()->notnull()
            ->string('password', 255)->notnull()
            ->datetime('created_at')->default('CURRENT_TIMESTAMP')
            ->createTable();

        $this->assertSame(
            'CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255) NOT NULL UNIQUE,'
            . ' email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,'
            . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP)',
            $sql
        );
    }

    public function testForeignKeyUsesExistingColumn(): void
    {
        $sql = (new MigrateClass('mysql'))->name('posts')
            ->string('title')->notNull()
            ->text('content')
            ->int('user_id')->notNull()
            ->foreignKey('user_id', 'users', 'id', 'cascade')
            ->datetime('published_at')->nullable()
            ->boolean('is_published')->default(0)
            ->toSql();

        $this->assertSame(
            'CREATE TABLE posts (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT,'
            . ' user_id INT NOT NULL, published_at DATETIME NULL, is_published TINYINT(1) DEFAULT 0,'
            . ' FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)',
            $sql
        );
    }

    public function testForeignKeyCreatesMissingColumn(): void
    {
        $sql = (new MigrateClass('mysql'))->name('comments')->foreignKey('post_id', 'posts')->toSql();

        $this->assertSame(
            'CREATE TABLE comments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT,'
            . ' FOREIGN KEY (post_id) REFERENCES posts(id))',
            $sql
        );
    }

    public function testChecksDefaultsAndTypes(): void
    {
        $sql = (new MigrateClass('mysql'))->name('products')->ifNotExists()
            ->string('name', 100)->notNull()
            ->decimal('price', 10, 2)->notNull()
            ->int('stock')->notNull()->default(0)
            ->string('status', 20)->default("it's new")
            ->json('meta')->nullable()
            ->timestamps()
            ->check('price > 0')
            ->check('stock >= 0')
            ->toSql();

        $this->assertSame(
            'CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,'
            . " price DECIMAL(10, 2) NOT NULL, stock INT NOT NULL DEFAULT 0, status VARCHAR(20) DEFAULT 'it''s new',"
            . ' meta JSON NULL, created_at DATETIME NULL, updated_at DATETIME NULL,'
            . ' CHECK (price > 0), CHECK (stock >= 0))',
            $sql
        );
    }

    public function testDriverSpecificSyntax(): void
    {
        $build = function (string $driver): string {
            return (new MigrateClass($driver))->name('t')->boolean('ok')->default(true)->text('body')->toSql();
        };

        $this->assertSame(
            'CREATE TABLE t (id SERIAL PRIMARY KEY, ok BOOLEAN DEFAULT TRUE, body TEXT)',
            $build('pgsql')
        );
        $this->assertSame(
            'CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, ok INTEGER DEFAULT 1, body TEXT)',
            $build('sqlite')
        );
        $this->assertSame(
            'CREATE TABLE t (id INT IDENTITY(1,1) PRIMARY KEY, ok BIT DEFAULT 1, body NVARCHAR(MAX))',
            $build('sqlserver')
        );
    }

    public function testNameStartsAFreshTable(): void
    {
        $table = new MigrateClass('mysql');
        $table->name('a')->string('x')->check('1 = 1');

        $this->assertSame(
            'CREATE TABLE b (code VARCHAR(10) NOT NULL)',
            $table->name('b', false)->string('code', 10)->notNull()->toSql()
        );
    }

    public function testDropTable(): void
    {
        $table = new MigrateClass('mysql');

        $this->assertSame('DROP TABLE IF EXISTS users', $table->dropTable('users'));
        $this->assertSame('DROP TABLE IF EXISTS posts', $table->name('posts')->dropTable());
    }

    public function testInvalidUsageIsRejected(): void
    {
        $cases = [
            [InvalidArgumentException::class, function () { new MigrateClass('mongodb'); }],
            [InvalidArgumentException::class, function () { (new MigrateClass())->name('t')->string('a')->string('a'); }],
            [InvalidArgumentException::class, function () { (new MigrateClass())->name('t')->foreignKey('a', 'b', 'id', 'explode'); }],
            [InvalidArgumentException::class, function () { (new MigrateClass())->name('t')->decimal('a', 2, 5); }],
            [LogicException::class, function () { (new MigrateClass())->string('a'); }],
            [LogicException::class, function () { (new MigrateClass())->name('t', false)->notNull(); }],
            [LogicException::class, function () { (new MigrateClass())->name('t', false)->toSql(); }],
        ];

        foreach ($cases as $i => [$exception, $case]) {
            try {
                $case();
                $this->fail("Case #$i did not throw");
            } catch (InvalidArgumentException | LogicException $e) {
                $this->assertInstanceOf($exception, $e, "Case #$i");
            }
        }
    }

    public function testCreateAndDropAgainstSqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not available');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $table = new MigrateClass('sqlite', $pdo);
        $table->name('users')->string('email')->unique()->notNull()->timestamps()->createTable();
        $table->name('posts')
            ->string('title')->notNull()
            ->decimal('price')->default(0)
            ->foreignKey('user_id', 'users', 'id', 'CASCADE')
            ->check('price >= 0')
            ->createTable();

        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
        $pdo->exec("INSERT INTO posts (title, user_id) VALUES ('hello', 1)");
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());

        $pdo->exec('DELETE FROM users');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());

        $table->dropTable('posts');
        $this->assertSame(
            ['users'],
            $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('users', 'posts')")
                ->fetchAll(PDO::FETCH_COLUMN)
        );
    }
}
