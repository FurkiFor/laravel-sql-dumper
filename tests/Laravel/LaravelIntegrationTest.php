<?php

namespace Furkifor\SqlDumper\Tests\Laravel;

use Furkifor\SqlDumper\Laravel\SqlDumperServiceProvider;
use Furkifor\SqlDumper\QueryLog;
use Furkifor\SqlDumper\SqlDumper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class LaravelIntegrationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [SqlDumperServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('sql-dumper.log', true);
        $app['config']->set('sql-dumper.slow_ms', null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('age');
            $table->boolean('active')->default(true);
        });

        DB::table('users')->insert([
            ['name' => 'Ali', 'age' => 17, 'active' => true],
            ['name' => "O'Brien", 'age' => 30, 'active' => true],
            ['name' => 'Zeynep', 'age' => 41, 'active' => false],
        ]);
    }

    public function testQueryBuilderMacro(): void
    {
        $query = DB::table('users')->where('age', '>', 18)->where('name', "O'Brien");

        $this->assertSame(
            'select * from "users" where "age" > 18 and "name" = \'O\'\'Brien\'',
            $query->rawSql()
        );
    }

    public function testEloquentMacroReturnsStringAndAppliesGlobalScopes(): void
    {
        $this->assertSame(
            'select * from "users" where "age" >= 18 and "active" = 1',
            ScopedUser::where('age', '>=', 18)->rawSql()
        );
    }

    public function testDumpSqlPrintsAndKeepsChaining(): void
    {
        ob_start();
        $names = ScopedUser::where('age', '>=', 18)->dumpSql()->pluck('name')->all();
        $output = ob_get_clean();

        $this->assertSame('select * from "users" where "age" >= 18 and "active" = 1' . PHP_EOL, $output);
        $this->assertSame(["O'Brien"], $names);
    }

    public function testRawSqlRunsAndReturnsTheSameRows(): void
    {
        $query = DB::table('users')->whereIn('age', [17, 41])->orderBy('age');

        $this->assertSame(
            $query->pluck('name')->all(),
            array_column(array_map('get_object_vars', DB::select($query->rawSql())), 'name')
        );
    }

    public function testQueryLogRecordsExecutedQueries(): void
    {
        $log = QueryLog::start();

        DB::table('users')->where('age', '>', 18)->get();
        foreach ([1, 2, 3] as $id) {
            DB::table('users')->where('id', $id)->first();
        }
        $log->stop();
        DB::table('users')->count();

        $this->assertCount(4, $log);
        $this->assertSame('select * from "users" where "age" > 18', $log->toRawSql()[0]);
        $this->assertSame('select * from "users" where "id" = 3 limit 1', $log->toRawSql()[3]);
        $this->assertSame(['select * from "users" where "id" = ? limit 1' => 3], $log->duplicates());
        $this->assertGreaterThanOrEqual(0.0, $log->totalTime());
        $this->assertSame('testing', $log->all()[0]['connection']);
        $this->assertStringContainsString('repeated 3x: select * from "users" where "id" = ? limit 1', $log->report());
    }

    public function testQueryLogSingletonFromContainer(): void
    {
        $log = $this->app->make(QueryLog::class);
        DB::table('users')->where('name', 'Ali')->get();

        $this->assertSame($log, $this->app->make(QueryLog::class));
        $this->assertSame(['select * from "users" where "name" = \'Ali\''], $log->toRawSql());
    }

    public function testConfiguredLoggingWritesRawSqlToTheLog(): void
    {
        Log::spy();

        DB::table('users')->where('age', '>', 18)->get();

        Log::shouldHaveReceived('debug')->withArgs(function ($message, $context = []) {
            return $message === 'select * from "users" where "age" > 18'
                && ($context['connection'] ?? null) === 'testing'
                && array_key_exists('time_ms', $context);
        })->once();
    }

    public function testStandaloneBuilderUsesTheLaravelConnection(): void
    {
        $rows = SqlDumper::table('users')->select('name')->where('age', '>', 18)->orderBy('age')->execute();

        $this->assertSame([['name' => "O'Brien"], ['name' => 'Zeynep']], $rows);
    }
}

class ScopedUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected static function booted()
    {
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('active', true);
        });
    }
}
