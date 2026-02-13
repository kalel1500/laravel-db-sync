# Tests

## 1. Tests Unitarios (Lógica Aislada)

Estos tests no tocan la base de datos (o usan mocks) y sirven para validar que los algoritmos de transformación funcionan correctamente.

### Capa de Dominio: Esquemas y Drivers

* `HasShortNamesTest`:
  * Verificar la generación de hashes de 30 caracteres para Oracle.
  * Validar que el nombre del índice sea determinista (mismo input, mismo output).
* `TableSchemaBuilderTest`:
  * Validar que mapea correctamente métodos de Blueprint (`string`, `integer`, `decimal`).
  * Probar la lógica de inferencia de tablas en `guessReferencedTable` (ej: `user_id` -> `users`).
* `BaseDriverTest`:
  * Verificar que `getTableFullName` respeta el prefijo de la conexión.

### Capa de Dominio: Datos
* `TableDataCopierTest` (Resolución):
  * Validar que los métodos especiales de Laravel (`timestamps`, `softDeletes`, `rememberToken`) se transforman en sus nombres de columna reales.
  * Probar que `resolveCaseTransforms` detecta correctamente las reglas de mayúsculas/minúsculas.

## 2. Tests de Integración (Flujo Real)

Estos tests utilizan las conexiones `source` y `target` configuradas en el `TestCase` para realizar operaciones reales.

### Sincronización Core

* `TableSynchronizerTest`:
  * **Swap de tablas:** Verificar que la tabla temporal se crea, se llena y finalmente se renombra a la original.
  * **Limpieza por error:** Confirmar que si la copia de datos falla, la tabla temporal es eliminada para no dejar "basura".
  * **Validación de FKs:** Asegurar que lanza excepción si hay claves foráneas autorreferenciadas con tabla temporal activada.
* `TableSyncCoordinatorTest`:
  * **Sistema de Bloqueo:** Probar que si una tabla se está sincronizando, una segunda llamada lanza la excepción de "Already being synced".
  * **Registro de Errores:** Verificar que si algo falla, el error y el stack trace se guardan correctamente en la tabla `dbsync_table_runs`.

### Flujos de Aplicación

* `DatabaseSyncExecutorTest`:
  * Sincronización por ID de conexión específica.
  * Sincronización por ID de tabla específica.
  * Sincronización masiva (todas las activas).
* `DbsyncRunCommandTest`:
  * Verificar que el comando de Artisan dispara el Job correctamente con los parámetros pasados.

### Drivers Específicos (Compatibilidad)

Aunque usamos SQLite para los tests generales, deberíamos emular comportamientos:
* `SQLiteDriverIntegrationTest`: Específicamente probar el `PRAGMA foreign_keys = OFF` durante el drop.
* `OracleDriverLogicTest`: Probar (mediante mocks o sintaxis compatible) el intento de reinicio de secuencias e identidad.

## 3. Matriz de Casos de Uso (Checklist)

| Categoría          | Test de Caso de Uso                                                                    | Estado       |
|--------------------|----------------------------------------------------------------------------------------|--------------|
| **Básico**         | Sincronización de tabla simple (sin FKs)                                               | 🔄 Pendiente |
| **Transformación** | Sincronización con `case_transform` (UPPER/lower)                                      | 🔄 Pendiente |
| **Seguridad**      | Intentar sincronizar tabla inexistente en origen                                       | 🔄 Pendiente |
| **Rendimiento**    | Sincronización con `batch_size` específico                                             | 🔄 Pendiente |
| **Filtros**        | Uso de `source_query` personalizada en lugar de tabla completa                         | 🔄 Pendiente |
| **Resiliencia**    | Verificar `min_records`: no hacer swap si el origen tiene menos datos de los esperados | 🔄 Pendiente |
