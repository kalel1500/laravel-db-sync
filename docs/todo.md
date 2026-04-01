# Tareas pendientes

- [ ] Preguntar como permitir que el usuario pueda modificar el builder si quiere controlar mas en detalle la creación en ciertos lenguajes

# A futuro

## Copy Source

### `source_config`

* `table`
* `virtual`
* `static`      (futuro)
* `computed`    (futuro)

### `source_config`

* `{ "type": "uuid" }`
* `{ "type": "ulid" }`
* `{ "value": "foo" }`                                              (futuro)
* `{ "expression": "NOW()" }`                                       (futuro)
* `{ "expression": "CONCAT(first_name, ' ', last_name)" }`          (futuro)
* `{ "type": "hash", "algo": "sha256", "columns": ["email"] }`      (futuro)
* `{ "type": "map", "from": "status", "map": { "A": 1, "B": 2 } }`  (futuro)
* `{ "type": "coalesce", "columns": ["a", "b", "c"] }`              (futuro)
