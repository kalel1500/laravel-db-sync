# Release Notes

## [Unreleased](https://github.com/kalel1500/laravel-db-sync/compare/v0.1.3-beta.0...master)

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
