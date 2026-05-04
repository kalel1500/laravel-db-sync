# Release Notes

## [Unreleased](https://github.com/kalel1500/laravel-db-sync/compare/v0.7.0-beta.2...master)

## [v0.7.0-beta.2](https://github.com/kalel1500/laravel-db-sync/compare/v0.7.0-beta.1...v0.7.0-beta.2) - 2026-05-04

### Fixed

* The `alias` has been removed from the `TableDataCopier` query (when the `source_query` field is not null) because that syntax does not exist.

## [v0.7.0-beta.1](https://github.com/kalel1500/laravel-db-sync/compare/v0.6.0-beta.1...v0.7.0-beta.1) - 2026-04-02

### ⚠️ Breaking Changes

* Migrations have been modified
  * Added new columns `source` and `source_config` to the `dbsync_columns` table.
    * `source` defines where the column value comes from (`table` or `virtual`).
    * `source_config` stores generator configuration (e.g. `{ "type": "uuid" }`).
  * Renamed `insert_row_by_row` to `has_large_text_values_in_oracle`.
* **Action Required**: 
  * Update your existing migrations manually
  * Or run `php artisan migrate:fresh` (⚠️ this will wipe your data)
  * Or manually add and rename the columns:
    * add `source` in `dbsync_columns`: `$table->string('source')->default('table');`
    * add `source_config` in `dbsync_columns`: `$table->json('source_config')->nullable();`
    * rename `insert_row_by_row` to `has_large_text_values_in_oracle` in `dbsync_tables`

### Added

* New `SchemaFactory` interface for the `SchemaManager` class.

### Changed

* Internal `DbsyncSchema` refactor:
  * The `SchemaFactory` interface is now used in the `getFacadeAccessor` of the `DbsyncSchema` facade.
  * Renamed clases:
    * `SchemaManager` → `SchemaConnection`
    * `SchemaConnection` → `SchemaManager`
* A large part of the `TableDataCopier` class has been remade:
  * **Refactored to a metadata-driven architecture**:
    * Introduced a centralized `resolveColumnsMeta()` method that normalizes all column definitions into a unified structure.
    * All column-related logic (selection, insertion, transformations, strategy resolution) now relies on this metadata instead of raw model access.
  * **Virtual Columns Support**:
    * Columns can now be excluded from the source query (`SELECT`) and generated dynamically during processing.
    * `transformRow()` now handles:
      * Virtual value generation (e.g., UUID/ULID)
      * Case transformations
      * Final filtering of insertable columns
    * Ensures strict control over which columns are inserted vs computed.
  * **Introduced row processing context:**
    * Added `RowProcessingContext` to avoid recalculating per-row logic.
    * Precomputes:
      * insertable columns
      * case transformations
      * virtual generators
    * Eliminates repeated computation inside loops and improves performance consistency.
  * **Strategy resolution now uses normalized metadata:**
    * `resolveStrategy()` no longer depends on raw `DbsyncColumn`.
    * Uses enriched metadata (e.g. `is_primary`, `is_auto_increment`, `is_nullable`, etc.).
    * Automatically ignores virtual columns when selecting chunking strategies.
  * **Improved handling of Laravel Blueprint methods:**
    * Added support for additional schema methods:
      * `uuid`, `ulid`
      * `nullableTimestamps`, `nullableTimestampsTz`, `datetimes`
      * `softDeletesTz`, `softDeletesDatetime`
      * `morphs`, `nullableMorphs`
    * Ensures correct column name resolution in all cases.
  * **Minimum Records Check Optimized**:
    * Now only executed when defined.
    * Uses a limited query instead of full `COUNT(*)`, reducing query cost on large datasets.
  * **Insert Logic Refactored**:
    * Moved insert responsibility from `DbsyncSchema` into `TableDataCopier::insert()`.
    * Decouples schema-level utilities from table-specific logic.
  * **Buffer Handling Lifecycle Improved**:
    * Explicitly re-enables PDO buffering after processing (`enableBuffer()`), ensuring connection state consistency.
  * **Renamed insert configuration flag:**
    * `insert_row_by_row` → `has_large_text_values_in_oracle`
    * Makes intent explicit and avoids exposing implementation details to users.

### Removed

* The `insert` method of the `SchemaConnection` (which was accessed through the `DbsyncSchema` facade) has been removed.
  * The three methods for inserting drivers have also been removed:
    * `insertBulk`
    * `insertRowByRow`
    * `insertAuto`

### Fixed

* **Strategy Resolution Edge Cases**:
    * Fixed incorrect behavior when resolving strategies with filtered columns or partial configuration.
    * Ensured invalid column references throw explicit exceptions.
* **Performance Issues in Large Datasets**:
    * Avoided unnecessary full table scans when `min_records` is not configured.
* The definition of the `disableBuffer` method has been removed from the `PostgresDriver` and `SqlServerDriver` drivers because those engines are lazy by default. You only need to define the method in the `MySQLDriver` driver.

---

## [v0.6.0-beta.1](https://github.com/kalel1500/laravel-db-sync/compare/v0.5.0-beta.0...v0.6.0-beta.1) - 2026-03-27

### ⚠️ Breaking Changes

* **Migration Update Required**: A new `copy_strategy` JSON column has been added to the `dbsync_tables` table. **Action Required:** If you have already published migrations:
  * Update your existing `create_dbsync_tables` migration manually
  * Or run `php artisan migrate:fresh` (⚠️ this will wipe your data)
  * Or manually add the column: `$table->json('copy_strategy')->nullable();`
* **Cache Tables Removed**: The internal migration for cache tables has been removed to avoid conflicts with Laravel's native cache migration.
  * Use `php artisan make:cache-table` if needed.

### Added

* **Memory Optimization (Unbuffered Queries)**: Introduced `disableBuffer()` in `DbsyncSchema`. The copier now automatically disables PDO row buffering (specifically for MySQL and SQL Server) to prevent `memory_limit` exhaustion when syncing large tables.
* **Execution Strategy System:** Introduced a new `copy_strategy` JSON field in `dbsync_tables` to control how data is copied. Supported strategies:
  * `chunkById` → index-based batching
  * `chunk` → offset-based batching
  * `cursor` → streaming via database cursor
* **Automatic Strategy Resolution**: Added `resolveStrategy()` to determine the optimal copy strategy when not fully configured. Resolution priority:
  1. Primary / auto-increment / unique columns → `chunkById`
  2. Fallback → `cursor`
* **Cursor-Based Streaming**: Added support for `$query->cursor()` to process large datasets without loading them into memory. Enables efficient processing for tables without suitable indexing.

### Changed

* **Query-Based Processing**: The `TableDataCopier` now performs `chunk()` or `cursor()` directly on the Database Query Builder instead of Laravel Collections, drastically reducing RAM usage.
* **Adaptive Data Copy Logic**: Data copying now dynamically selects the best execution strategy instead of always using collection chunking.
* **Source Query Wrapper**: Custom SQL queries are now wrapped as subqueries (`(SELECT ...) as __dbsync_sub__`) to support consistent pagination and ordering.
* **Relicensed to MPL-2.0**: The package license has been changed from `GPL-3.0-or-later` to `Mozilla Public License 2.0 (MPL-2.0)`. This provides more flexibility for commercial and proprietary projects while ensuring that improvements to the package's core files remain open source.

### Fixed

* **Memory Leaks (OOM)**: Resolved critical "Out of Memory" errors when loading full datasets of large tables into collections.

## [v0.5.0-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.4.1-beta.1...v0.5.0-beta.0) - 2026-02-27

### Added

* Validación de Drivers: Sistema de validación en el constructor de los drivers para asegurar que las dependencias necesarias y las versiones de los motores sean compatibles antes de iniciar la sincronización.
  * (breaking) Ahora se valida que el paquete `yajra/laravel-oci8` esté instalado y actualizado a la versión `^12.10`.
  * (breaking) También se valida que en la configuración de oracle, la key `server_version` sea `12c` o superior.

### Changed

* (breaking) Se ha simplificado el método `truncate` en el `SchemaManager` eliminando la captura silenciosa de errores y quitando el segundo intento de truncado automático que ignoraba el reseteo de contadores. Ahora, cualquier fallo en la ejecución es visible para el usuario, garantizando transparencia en el proceso.

### Removed

* (breaking) Se elimina el soporte para versiones de Oracle inferiores a 12c (como 11g o 10g), ya que no cuentan con columnas de identidad nativas y no es posible garantizar su estabilidad sin pruebas de integración.

### Migration notes (Breaking Changes Summary)

* **Requisitos de Oracle**: Si utilizas Oracle, ahora es obligatorio tener instalado el paquete `yajra/laravel-oci8` en su versión `^12.10` y contar con un servidor versión `12c` o superior. Además, también debes modificar tu configuración de conexión para incluir la key `server_version` con el valor `12c` o superior.
* **Gestión de Errores**: Si anteriormente tus procesos dependían de que `truncate` fallara silenciosamente y continuara, ahora deberás gestionar las excepciones, ya que el paquete prioriza la integridad y visibilidad de los errores de base de datos.

## [v0.4.1-beta.1](https://github.com/kalel1500/laravel-db-sync/compare/v0.4.1-beta.0...v0.4.1-beta.1) - 2026-02-18

### Fixed

* (fix) Se ha envuelto la eliminación de bloqueo (`$lock->release()`) en un `tryCatch` para evitar que un error en la liberación del lock corte la ejecución del proceso.
  <br>En caso de error, se registra un log de Laravel para facilitar la detección y resolución del problema.

## [v0.4.1-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.4.0-beta.0...v0.4.1-beta.0) - 2026-02-17

### Added

* Nuevo paso en el proceso de sincronización para **sincronizar el autoincremental** tras copiar los datos.
  <br>Cuando se copian registros con valores explícitos en la columna _identity/autoincrement_, el contador interno puede quedar desfasado (especialmente en Oracle), provocando errores en inserciones posteriores.
  <br>Se añade soporte completo para recalcularlo automáticamente:
  * Nuevo método `syncIdentity` en la interfaz `SchemaDriver`.
  * Implementación de `syncIdentity` en todos los drivers.
  * Nuevo método `syncIdentity` en `SchemaManager` y disponible a través de la fachada `DbsyncSchema`.
  * Nuevo método `syncIdentity` en `TableSchemaBuilder`, que detecta si la tabla tiene una columna autoincremental definida en las tablas `dbsync_*`.
  * Ejecución automática de `syncIdentity` tras copiar los datos en `TableSynchronizer`, tanto en:
    * `syncUsingDrop`
    * `syncUsingTemporalTable`

### Changed

* (refactor) Refactor interno del sistema de nombres de tabla en los drivers:
  * Nuevo método protegido `wrapTable` en `BaseDriver`, que delega en el `QueryGrammar` de Laravel para envolver correctamente los identificadores según el motor (_MySQL_, _PostgreSQL_, _Oracle_, etc.).
  * Uso de `wrapTable` en las consultas `DROP` y `ALTER` de los drivers de _Oracle_ y _PostgreSQL_ para garantizar compatibilidad y quoting correcto.
  * Renombrado del método `getTableFullName` a `getDictionaryTableName` en `BaseDriver` para reflejar mejor su responsabilidad.
  * Sobreescritura de `getDictionaryTableName` en `OracleDriver` para normalizar el nombre de tabla en mayúsculas cuando se consulta el diccionario interno del motor.

>Este cambio mejora la coherencia interna y reduce la duplicación de lógica específica por motor.

### Removed

* (fix) Eliminada la comprobación de existencia de tabla en el método `forceDrop` del `OracleDriver`.
  <br>La validación ya se realiza previamente en `SchemaManager` mediante `hasTable` de Laravel, por lo que la comprobación adicional en el driver era redundante.

### Fixed

* Se corrige el problema de desincronización del autoincremental tras copiar datos con IDs explícitos, que podía provocar errores en inserciones posteriores (especialmente en Oracle).

## [v0.4.0-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.3.1-beta.0...v0.4.0-beta.0) - 2026-02-16

### Added

- Nueva columna `insert_row_by_row` en la tabla `dbsync_tables` (boolean, default `false`).
  - Permite forzar la inserción fila por fila dentro de una transacción cuando sea necesario. Pensado principalmente para resolver limitaciones específicas de Oracle al trabajar con valores de texto muy largos.
  - Nuevo método `insert` en la clase `SchemaManager` que según el campo `insert_row_by_row` llama a uno de los nuevos métodos de la interfaz `SchemaDriver`:
    - `insertBulk`
    - `insertRowByRow`
    - `insertAuto`

### Fixed

- Se ha arreglado el error `ORA-01790` en Oracle cuando se intentan sincronizar campos `CLOB` o strings de más de 4000 caracteres en modo masivo.
  - **(Mejora vinculada):** Para mitigar el error se ha definido la columna `insert_row_by_row` y asi evitar el insert masivo en Oracle.

### Notas de migración (Breaking Changes Summary)

- (breaking) Se ha modificado la migración de la tabla `dbsync_tables`. Si ya tenías el paquete instalado, deberás añadir manualmente la columna `insert_row_by_row`

## [v0.3.1-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.3.0-beta.0...v0.3.1-beta.0) - 2026-02-10

### Added

* **Gestión de Ciclo de Vida de Constraints:** Implementación de un sistema de regeneración automática de Foreign Keys (`rebuildDependentForeignKeys` en `TableSchemaBuilder`). Este mecanismo detecta qué claves externas apuntaban a la tabla sincronizada y las reconstruye tras el proceso de carga (ya que este realiza un borrado en cascada).
  * **Inteligencia por driver:** Solo actúa en motores con borrado destructivo (_Oracle_, _Postgres_, _SQL Server_), optimizando el rendimiento en _MySQL/MariaDB_.
  * **Garantía en estrategia temporal:** La reconstrucción se ejecuta solo después del renombre final de la tabla para asegurar que las claves apunten al nombre real y no al temporal.
* **Nueva clase `SchemaManager`:** Se expone una herramienta pública para realizar operaciones de estructura de forma segura entre diferentes motores de base de datos.
  * **Arquitectura Multi-driver:** Implementación basada en el patrón Strategy con clases especializadas por motor (_Oracle_, _Postgres_, _SQL Server_, etc.), garantizando una gestión de esquema aislada y mantenible.
  * **Método `forceDrop`:** Realiza un borrado seguro de tablas con dependencias activas (usando `CASCADE` o limpieza manual según el driver). Respeta automáticamente el prefijo de tablas configurado en Laravel.
  * **Método `truncate`:** Vacía la tabla y resetea los contadores de ID autoincrementales (Identity/Sequence) de forma específica para cada motor de base de datos.
  * **Nueva Fachada:** Se ha creado la Fachada `DbsyncSchema` que apunta al `SchemaManager` para poder acceder de forma fluida a sus métodos de gestión de esquemas.
  * **Soporte Multiconexión:** El método `connection()` actúa como un gestor de instancias, permitiendo trabajar con múltiples conexiones simultáneamente de forma segura mediante la Fachada `DbsyncSchema`.
    * **Arquitectura de Gestión de Instancias:** Implementación basada en el patrón `Factory/Manager`, que garantiza el aislamiento total entre múltiples conexiones. Cada conexión gestionada es inmutable, evitando la persistencia de estado o conflictos entre drivers al operar con distintas bases de datos en la misma ejecución.


### Changed

* **(refactor) Reubicación de lógica de esquema:** Se traslada el método `hasSelfReferencingForeignKey` desde `TableSynchronizer` hacia `TableSchemaBuilder`. Este cambio consolida toda la responsabilidad de análisis y validación de estructura de tablas en una única clase especializada.
* **(refactor) Centralización de Naming:** Se extrae la lógica de generación de nombres cortos al nuevo Trait `HasShortNames`, asegurando que los índices sean idénticos en la creación y en la reconstrucción.
* **(refactor) Optimización de Inyección de Dependencias:** El método `sync` ahora gestiona centralmente la `Connection` y el `Builder` de Laravel, mejorando la eficiencia y reduciendo la redundancia de código en las estrategias de sincronización.

### Fixed

* **Borrado Seguro Multi-Driver:** Corrección del error al borrar las tablas, ya que el método `disableForeignKeyConstraints()` de Laravel no es suficiente para eliminar tablas con dependencias activas en _Oracle_, _Postgres_ y _SQL Server_.
  * Se implementa `DbsyncSchema::forceDrop()` al realizar el borrado de las tablas durante el proceso de sincronización.
  * **(Mejora vinculada):** Para mitigar la pérdida de claves tras el borrado en cascada, se utiliza el nuevo sistema de reconstrucción de FKs (`rebuildDependentForeignKeys`) que restaura las relaciones de tablas ajenas tras la sincronización.
* **Precisión en la detección de autorreferencias:** Se corrige `hasSelfReferencingForeignKey` para priorizar el nombre de tabla definido en el modificador `constrained()`, estableciendo un orden de prioridad lógico (Flag BD > Parámetro explícito > Inferencia).

## [v0.3.0-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.2.0-beta.0...v0.3.0-beta.0) - 2026-02-07

### Added

* **(breaking) Nueva propiedad `self_referencing` en definiciones de columna:** Se añade soporte explícito para identificar columnas que referencian a la misma tabla, optimizando la lógica de detección de dependencias.
* **Documentación ampliada:** Actualización completa del `README.md` con ejemplos detallados de configuración de columnas y aclaraciones sobre el comportamiento de claves foráneas autorreferenciales.

### Changed

* **(breaking) Parámetros y códigos nullables:**
  * Se permite que el campo `parameters` en `dbsync_columns` sea `nullable`, eliminando la necesidad de declarar arrays vacíos para métodos sin argumentos.
  * Se establece el campo `code` como `nullable`, manteniendo su utilidad para seeders pero eliminando su obligatoriedad en producción.
  * **Nota:** Es recomendable actualizar el esquema de la base de datos para reflejar estos cambios en las columnas.
* **Optimización de Naming:**
  * Eliminación del uso de `uniqid()` en la creación de tablas temporales para mejorar la legibilidad del esquema, confiando en el proceso de drop previo.
  * Mejora del hash en `generateShortName` mediante la inclusión de un separador `:` para prevenir colisiones matemáticas por concatenación simple de strings.

### Fixed

* **Compatibilidad Estricta con Oracle & Naming:**
  * **Generación de identificadores cortos y únicos:** Implementación de lógica para asegurar que los nombres de índices y constraints no superen los límites de Oracle y sean únicos por ejecución mediante el uso del nombre de la tabla temporal.
* **Integridad de Esquema y Validaciones:**
  * **(breaking) Validación de tipos en claves compuestas:** Ahora se exige estrictamente una estructura de _array de arrays_ para `indexes` y `unique_keys` en `dbsync_tables`, evitando errores de definición en claves compuestas múltiples. **Nota:** El usuario debe adaptar sus datos existentes de `["col1", "col2"]` a `[["col1"], ["col2"]]` si desea índices independientes.
  * **(breaking) Restricción de métodos de datos:** Bloqueo de métodos de restricción (`index`, `unique`, `primary`, `foreign`) dentro de la definición de columnas para forzar su uso a través de modificadores o de la tabla de configuración global.
* **Lógica de Sincronización y Ciclo de Vida:**
  * **Gestión de claves autorreferenciales:** Se añade una verificación de seguridad que impide el uso de la estrategia de tabla temporal si existen FKs que apuntan a la propia tabla, evitando bloqueos de integridad.
  * **Eliminación segura de tablas:** Deshabilitación temporal de Foreign Keys antes de ejecutar el `drop` de tablas en `TableSynchronizer` para prevenir errores de restricción.
* **Refactor de TableDataCopier y SchemaBuilder:**
  * **Resolución inteligente de nombres de columna:** Se prioriza siempre el parámetro definido por el usuario sobre los nombres por defecto de Laravel (ej. permitir sobreescribir el nombre en `id()`).
  * **Soporte para métodos especiales:** Inclusión de `softDeletes` y `rememberToken` en la lista de métodos exceptuados de requerir nombre de columna obligatorio.

### Notas de migración (Breaking Changes Summary)

1. **Esquema:** Ejecutar migraciones para añadir `self_referencing` y permitir nulos en `parameters` y `code`.
2. **Datos:** Actualizar `dbsync_tables` para que `indexes` y `unique_keys` sean arrays de arrays (ej: `[["columna_a", "columna_b"]]`).
3. **Definiciones:** Asegurarse de que `dbsync_columns` no contenga métodos como `primary`, `index`, `unique` o `foreign` en el campo `method`, desplazándolos a la lógica de modificadores.


## [v0.2.0-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.3-beta.1...v0.2.0-beta.0) - 2026-02-02

### Changed

* (breaking) Añadir la columna code a la tabla `dbsync_columns` para poder buscar fácilmente las columnas. De esta forma los inserts se pueden hacer con ids dinámicos buscando la columna por el campo `code`.

### Fixed

* (fix) Eliminar la condición `!$this->app->configurationIsCached()` al cargar la configuración en el `DbsyncServiceProvider`, ya que Laravel lo maneja mejor automáticamente.

## [v0.1.3-beta.1](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.3-beta.0...v0.1.3-beta.1) - 2026-01-29

### Fixed

* (fix) Convertir las opciones del comando `DbsyncRunCommand` a `int` si no son `null` ya que el job `RunDatabaseSyncJob` solo acepta parámetros de tipo `int|null`.

## [v0.1.3-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.2-beta.0...v0.1.3-beta.0) - 2026-01-23

### Changed

* Manejar excepciones al copiar datos en `TableSynchronizer` y limpiar tabla temporal en caso de error.

### Fixed

* (fix) Eliminar transacción en el swap de la tabla temporal en `TableSyncCoordinator` ya que no es soportada por DDL en todos los motores.

## [v0.1.2-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.1-beta.0...v0.1.2-beta.0) - 2026-01-23

### Changed

* Adaptar `TableDataCopier`  para transformar los registros a mayúsculas o minúsculas según el valor del campo `case_transform`.
* Añadir la columna `case_transform`  en la tabla `dbsync_columns`.

## [v0.1.1-beta.0](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.0-beta.0...v0.1.1-beta.0) - 2026-01-22

### Changed

* Adaptar `TableDataCopier` para copiar solo las columnas definidas en la tabla `dbsync_columns` y asi poder crear tablas en el destino con menos columnas que en el origen.
* Manejar las excepciones al registrar errores en base de datos en el catch del `TableSyncCoordinator` dejando un log de Laravel si falla.

### Fixed

* (fix) Prevenir error cuando el mensaje de error o el rastro de error exceden la longitud máxima permitida en la base de datos:
  * Ampliar la longitud del campo `error_message` de la tabla `dbsync_table_runs` usando `longText` en la migración.
  * Limitar la longitud del campo `error_message` al guardar el log en el `TableSyncCoordinator`.
  * Limitar la longitud del campo `error_trace` al guardar el log en el `TableSyncCoordinator`.

## v0.1.0-beta.0 - 2026-01-19

Primera versión del paquete.
