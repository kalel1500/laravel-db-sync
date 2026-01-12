<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Thehouseofel\Dbsync\Domain\Contracts\DbsyncTableRepository;
use Thehouseofel\Dbsync\Domain\Contracts\SyncStrategy;
use Thehouseofel\Dbsync\Domain\Strategies\AlwaysRecreateStrategy;
use Thehouseofel\Dbsync\Domain\Strategies\TemporalTableStrategy;
use Thehouseofel\Dbsync\Infrastructure\Console\Commands\DbsyncRunCommand;
use Thehouseofel\Dbsync\Infrastructure\Repositories\Eloquent\EloquentDbsyncTableRepository;

class DbsyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! defined('DBSYNC_PATH')) {
            define('DBSYNC_PATH', realpath(__DIR__ . '/../'));
        }

        // Configuración - Mergear la configuración del paquete con la configuración de la aplicación, solo hará falta publicar si queremos sobreescribir alguna configuración.
        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(DBSYNC_PATH . '/config/dbsync.php', 'dbsync');
        }

        $this->registerSingletons();
    }

    protected function registerSingletons(): void
    {
        $this->app->singleton(DbsyncTableRepository::class, EloquentDbsyncTableRepository::class);

        $this->app->singleton(SyncStrategy::class, function (Application $app) {

            // For now, the AlwaysRecreateStrategy is always used.
            $class = AlwaysRecreateStrategy::class;

            // TODO: The logic of the CompareAndOptimizeStrategy still needs to be written.
            /*$class = match (config('dbsync.strategy')) {
                'recreate' => CompareAndOptimizeStrategy::class,
                'compare' => AlwaysRecreateStrategy::class,
            };*/

            return $class;
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMigrations();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) return;

        /*
         * -------------------
         * --- Migraciones ---
         * -------------------
         */

        $this->publishesMigrations([
            DBSYNC_PATH . '/database/migrations' => database_path('migrations'),
        ], 'dbsync-migrations');


        /*
         * -----------------------
         * --- Configuraciones ---
         * -----------------------
         */
        $this->publishes([
            DBSYNC_PATH . '/config/dbsync.php' => config_path('dbsync.php'),
        ], 'dbsync-config');
    }

    protected function registerCommands(): void
    {
        $this->commands([DbsyncRunCommand::class]);
    }

    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole() && config('dbsync.run_migrations')) {
            $this->loadMigrationsFrom(DBSYNC_PATH . '/database/migrations');
        }
    }
}
