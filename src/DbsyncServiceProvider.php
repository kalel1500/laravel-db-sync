<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync;

use Illuminate\Support\ServiceProvider;
use Thehouseofel\Dbsync\Infrastructure\Console\Commands\DbsyncRunCommand;

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
