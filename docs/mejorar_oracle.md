Actúa como un Ingeniero Principal de Software experto en optimización de bases de datos relacionales y Laravel (en particular, con integraciones híbridas usando Oracle a través de yajra/laravel-oci8).

Tengo un paquete de sincronización de bases de datos genérico en Laravel (`laravel-db-sync`). El paquete realiza streaming de datos desde un origen mediante Query Builder (`chunk`, `chunkById`, o `cursor`) y los vuelca en un destino usando `$connection->table($targetTable)->insert($rows)`.

### 🚨 El problema de rendimiento detectado
Al realizar pruebas empíricas insertando un volumen de unos 11,000 registros en una base de datos Oracle (sin ninguna bandera especial activa, utilizando el insert masivo estándar de Laravel), nos hemos topado con una anomalía de rendimiento drástica:
- Con `batch_size = 50` (lotes de 50 en 50): El proceso tarda **4 segundos** en total.
- Con `batch_size = 500` (lotes de 500 en 500): El proceso tarda **14 segundos** en total.

En un motor como MySQL o PostgreSQL, aumentar el tamaño del chunk a 500 reduciría los viajes de red y el tiempo de ejecución. Sin embargo, en Oracle el rendimiento empeora multiplicándose por más de tres.

### 🧠 Hipótesis y causas analizadas preliminarmente
Hemos deducido que el problema se debe a la arquitectura interna de Oracle y cómo procesa las sentencias masivas generadas por Eloquent/PDO:
1. **Costo exponencial de Hard Parsing:** Un insert de 500 filas con, por ejemplo, 10 columnas, genera una sola query gigantesca con 5,000 variables de sustitución (bindings). El *SQL Parser* de Oracle sufre una penalización exponencial de CPU intentando analizar sintácticamente esa consulta y calcular su plan de ejecución, tardando más en parsear que en escribir en disco.
2. **Saturación de buffers de red (SDU):** Estructuras tan masivas rompen los buffers estándar de comunicación (como el Session Data Unit de Oracle), forzando fragmentación a nivel de red para una sola petición.
3. **Falta de transacciones envolventes:** Cada callback de chunk ejecuta un insert aislado sin una transacción global explícita, magnificando el costo de las sentencias masivas mal digeridas por Oracle.

---

### 📂 Código actual de la clase encargada de la inserción:
A continuación te proporciono el método `insert` de mi clase `TableDataCopier`:

```php
protected function insert(Connection connection, DbsyncTable table, string targetTable, array rows): void
{
    \$hasTextLikeColumns = table->columns->contains(function (column) {
        return in_array(\$column->method, ['text', 'mediumText', 'longText']);
    });

    if (DbsyncSchema::connection(\$connection)->isOracle() && \(hasTextLikeColumns &&\)table->has_large_text_values_in_oracle) {
        connection->transaction(function () use (connection, targetTable, rows) {
            foreach (\$rows as \(row) {\)connection->table(targetTable)->insert(row);
            }
        });
    } else {
        \$connection->table(targetTable)->insert(rows);
    }
}
```

---

### 🎯 Lo que necesito de ti (Tu tarea)

Como eres un modelo de IA avanzado y potente, quiero que realices las siguientes tareas paso a paso:

1. **Análisis Crítico e Independiente:** Revisa mis conclusiones. Analiza a nivel de bajo nivel (PDO, driver Yajra y motor Oracle) si estás de acuerdo con que el Hard Parsing de miles de bindings en el `INSERT` masivo de Laravel es la causa raíz de la degradación, o si identificas otra limitación (como la sintaxis `INSERT ALL` o `UNION ALL` que Laravel genera por debajo para Oracle).
2. **Propuesta Arquitectónica:** Determina cuál es la solución más óptima para solucionar esto de raíz. Ten en cuenta que este es un **paquete genérico**, por lo que la configuración de `batch_size` de cara al usuario en la tabla de configuración debe respetarse (para controlar el consumo de memoria en PHP/Streaming), pero la inserción física en base de datos debería auto-protegerse si el driver es Oracle.
3. **Mejores Prácticas adicionales (Transacciones):** Evalúa si envolver las operaciones de inserción en transacciones automáticas controladas por lotes a nivel del driver destino reduciría aún más los tiempos de ejecución.
4. **Implementación de Código Completa:** Reescribe el método `insert` (o la lógica que consideres necesaria dentro de la clase) aplicando la solución ganadora. El código debe ser limpio, mantenible y estar listo para producción.

Dime si mi enfoque de subdividir el array de filas en sub-lotes más pequeños (ej. de 50 o 100) únicamente cuando la conexión de destino sea Oracle es la mejor vía, o si existe una alternativa técnica superior usando las capacidades del driver de Yajra. ¡Sorpréndeme!
