# Release Notes

## [Unreleased](https://github.com/kalel1500/laravel-db-sync/compare/v0.4.0-beta.0...master)

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
