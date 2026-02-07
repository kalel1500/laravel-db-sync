# Release Notes

## [Unreleased](https://github.com/kalel1500/laravel-db-sync/compare/v0.3.0-beta.0...master)

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
