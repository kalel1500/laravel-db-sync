<?php

namespace Thehouseofel\Dbsync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Thehouseofel\Dbsync\DbsyncServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ejecutar las migraciones propias del paquete en la conexión por defecto (target)
        $this->setUpDatabase();
    }

    /**
     * Define el entorno de la aplicación.
     */
    protected function getEnvironmentSetUp($app)
    {
        // Forzar la carga del archivo .env.testing si existe en la raíz del paquete
        if (file_exists(__DIR__ . '/../.env.testing')) {
            \Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing')->load();
        }

        // Driver que queremos probar (por defecto sqlite)
        $driver = env('DBSYNC_TEST_DRIVER', 'sqlite');

        // Conexión LOCAL (donde el paquete guarda sus tablas de control)
        // Normalmente esta siempre puede ser SQLite para ir rápido
        $app['config']->set('database.connections.target', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        // Conexión SOURCE (La que emula la BD externa que queremos sincronizar)
        // Aquí es donde entra Docker
        if ($driver === 'sqlite') {
            $app['config']->set('database.connections.source', [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]);
        } else {
            // Configuramos la conexión según lo que tengas en tu Docker
            $app['config']->set('database.connections.source', [
                'driver'   => $driver,
                'host'     => env('DBSYNC_TEST_HOST', '127.0.0.1'),
                'port'     => env('DBSYNC_TEST_PORT'),
                'database' => env('DBSYNC_TEST_DATABASE'),
                'username' => env('DBSYNC_TEST_USERNAME'),
                'password' => env('DBSYNC_TEST_PASSWORD'),
                // Opciones específicas para Oracle si usas oci8/pdo
                'service_name' => env('DBSYNC_TEST_SERVICE_NAME'),
            ]);
        }
    }

    /**
     * Cargar el Service Provider del paquete.
     */
    protected function getPackageProviders($app)
    {
        return [
            DbsyncServiceProvider::class,
        ];
    }

    /**
     * Limpieza y ejecución de migraciones.
     */
    protected function setUpDatabase()
    {
        // Cargamos las migraciones reales del paquete
        $migrations = [
            __DIR__ . '/../database/migrations/2025_12_21_000001_create_cache_table.php',
            __DIR__ . '/../database/migrations/2025_12_21_000002_create_dbsync_connections_table.php',
            __DIR__ . '/../database/migrations/2025_12_21_000003_create_dbsync_tables_table.php',
            __DIR__ . '/../database/migrations/2025_12_21_000004_create_dbsync_columns_table.php',
            __DIR__ . '/../database/migrations/2025_12_21_000005_create_dbsync_column_table_table.php',
            __DIR__ . '/../database/migrations/2025_12_21_000006_create_dbsync_table_runs_table.php',
        ];

        foreach ($migrations as $file) {
            if (file_exists($file)) {
                $migration = include $file;
                $migration->up();
            }
        }
    }
}
