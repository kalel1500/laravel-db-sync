<?php
namespace Thehouseofel\Dbsync\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Tests\TestCase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Application\DatabaseSyncExecutor;

class SyncIntegrationTest extends TestCase
{
    /** @test */
    public function it_can_sync_a_simple_table_from_source_to_target()
    {
        // 1. Preparar la tabla de origen con datos
        Schema::connection('source')->create('users_external', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('email_address');
        });

        DB::connection('source')->table('users_external')->insert([
            ['full_name' => 'Adrian Canals', 'email_address' => 'adrian@example.com'],
            ['full_name' => 'Test User', 'email_address' => 'test@example.com'],
        ]);

        // 2. Configurar el paquete mediante sus modelos
        $conn = DbsyncConnection::create([
            'source_connection' => 'source',
            'target_connection' => 'target',
            'active' => true,
        ]);

        $table = DbsyncTable::create([
            'connection_id' => $conn->id,
            'source_table' => 'users_external',
            'target_table' => 'users',
            'active' => true,
            'use_temporal_table' => true,
        ]);

        // Añadir columnas (mapeo)
        $colId = DbsyncColumn::create(['method' => 'id']);
        $colName = DbsyncColumn::create(['method' => 'string', 'parameters' => ['full_name']]);
        $colEmail = DbsyncColumn::create(['method' => 'string', 'parameters' => ['email_address']]);

        $table->columns()->attach([
            $colId->id => ['order' => 1],
            $colName->id => ['order' => 2],
            $colEmail->id => ['order' => 3],
        ]);

        // 3. Ejecutar la sincronización
        app(DatabaseSyncExecutor::class)->execute();

        // 4. Verificaciones (Asserts)
        $this->assertTrue(Schema::connection('target')->hasTable('users'));
        $this->assertEquals(2, DB::connection('target')->table('users')->count());
        $this->assertDatabaseHas('dbsync_table_runs', [
            'table_id' => $table->id,
            'status' => 'success',
            'rows_copied' => 2
        ], 'target'); // Importante chequear la tabla de logs
    }
}
