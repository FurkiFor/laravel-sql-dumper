<?php

namespace Furkifor\SqlDumper\Laravel;

use Furkifor\SqlDumper\QueryLog;
use Furkifor\SqlDumper\SqlDumper;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class SqlDumperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sql-dumper.php', 'sql-dumper');

        // app(QueryLog::class) starts recording the first time it is resolved.
        $this->app->singleton(QueryLog::class, function ($app): QueryLog {
            return (new QueryLog())->listenTo($app['db']);
        });
    }

    public function boot(): void
    {
        BuilderMacros::register();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/sql-dumper.php' => $this->app->configPath('sql-dumper.php'),
            ], 'sql-dumper-config');
        }

        if ($this->app['config']->get('sql-dumper.log')) {
            $this->logExecutedQueries();
        }
    }

    private function logExecutedQueries(): void
    {
        $config = $this->app['config'];
        $channel = $config->get('sql-dumper.channel');
        $slowMs = $config->get('sql-dumper.slow_ms');

        $this->app['db']->listen(static function (QueryExecuted $query) use ($channel, $slowMs): void {
            if ($slowMs !== null && $query->time < (float) $slowMs) {
                return;
            }

            $sql = SqlDumper::interpolate($query->sql, $query->connection->prepareBindings($query->bindings));
            $context = ['time_ms' => $query->time, 'connection' => $query->connectionName];

            if ($channel) {
                Log::channel($channel)->debug($sql, $context);
            } else {
                Log::debug($sql, $context);
            }
        });
    }
}
