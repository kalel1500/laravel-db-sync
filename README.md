# Database Sync for Laravel

<p align="center">
    <!-- <a href="https://github.com/kalel1500/laravel-db-sync/actions/workflows/tests.yml"><img src="https://github.com/kalel1500/laravel-db-sync/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a> -->
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/dt/kalel1500/laravel-db-sync" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/v/kalel1500/laravel-db-sync" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/l/kalel1500/laravel-db-sync" alt="License"></a>
</p>

A Laravel package to **safely synchronize tables and data from external databases** into your application database.

This package is designed to **pull data from other machines or systems** (MySQL, PostgreSQL, Oracle, etc.) into a Laravel application in a controlled, traceable, and production-ready way, without forcing your final domain schema to match the source.

It focuses on **data ingestion**, not on how that data is later processed inside your application.

---

## What this package does (and when to use it)

This package is useful when you need to:

* Import data from legacy systems
* Synchronize data from external servers
* Periodically ingest data from other databases
* Create staging tables fed by external sources
* Centralize data from multiple origins

It **does not replace migrations or ORMs**.
It solves a very specific problem: **bringing external data into your Laravel database safely and observably**.

---

## Installation

Install the package via Composer:

```bash
composer require kalel1500/laravel-db-sync
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=dbsync-migrations
php artisan migrate
```

---

## Example usage

### 1. Define an external connection

In `config/database.php`:

```php
'connections' => [
    'legacy_mysql' => [
        'driver'   => 'mysql',
        'host'     => '192.168.1.10',
        'database' => 'legacy_db',
        'username' => 'user',
        'password' => 'secret',
    ],
],
```

---

### 2. Fill package tables

#### `dbsync_connections`

| id | source_connection | target_connection |
|----|-------------------|-------------------|
| 1  | legacy_mysql      | mysql             |

---

#### `dbsync_tables`

| id | source_table | target_table | min_records | active | source_query | use_temporal_table | batch_size | primary_key | unique_keys | indexes            | connection_id |
|----|--------------|--------------|-------------|--------|--------------|--------------------|------------|-------------|-------------|--------------------|---------------|
| 1  | users        | users        | 300         | true   | null         | true               | 1000       | null        | null        | null               | 1             |
| 2  | roles        | roles        | 100         | true   | null         | false              | 500        | null        | null        | null               | 1             |
| 2  | types        | types        | 1           | true   | null         | false              | 500        | null        | null        | [["name", "slug"]] | 1             |

> Note on Composite Keys: The `unique_keys` and `indexes` fields must follow an "array of arrays" format: [["col1"], ["col2", "col3"]].

---

#### `dbsync_columns`

Example columns for `users` table:

| id | table_id | method    | parameters      | modifiers                                                   |
|----|----------|-----------|-----------------|-------------------------------------------------------------|
| 1  | 1        | id        | null            | null                                                        |
| 2  | 1        | string    | `["name"]`      | `["nullable"]`                                              |
| 2  | 1        | string    | `["email", 50]` | `["nullable", "unique"]`                                    |
| 2  | 1        | boolean   | `["is_active"]` | `[{"method": "default", "parameters": [true]}]`             |
| 2  | 1        | foreignId | `["type_id"]`   | `[{"method": "constrained", "parameters": ["user_types"]}]` |

#### `dbsync_column_table`

Example `users` columns:

| id | table_id | column_id | order |
|----|----------|-----------|-------|
| 1  | 1        | 1         | 1     |
| 2  | 1        | 2         | 2     |
| 2  | 1        | 3         | 3     |
| 2  | 1        | 4         | 4     |

---

### 3. Run the sync

Run all tables:

```bash
php artisan dbsync:run
```

Run a specific connection:

```bash
php artisan dbsync:run --connection=1
```

Run a specific table:

```bash
php artisan dbsync:run --table=2
```

Priority order when filtering:

1. table
2. connection

---

## Synchronization strategies

Each table defines **how it should be synchronized**.

### Drop & Recreate

Drops the destination table and recreates it. Downtime occurs during the data insertion phase.

**Pros**

* Simple
* Fast

**Cons**

* Data is unavailable during the sync

Used when: `use_temporal_table = false`

---

### Temporal Table (recommended for large tables)

* Creates a temporary table
* Loads all data into the temporary table
* Drops the original table
* Renames the temporary table

> Oracle Compatibility: This package automatically generates short, unique names (max 12 chars) for all indexes and constraints (e.g., unq_a1b2c3d4). This prevents naming collisions and "Identifier too long" errors during the rename process in Oracle.

**Pros**

* Minimizes downtime
* Safer for large datasets

**Cons**

* It does not support self-referential fks

Used when: `use_temporal_table = true`

---

## Important Constraints

### 1. Self-Referencing Foreign Keys

The `temporal_table` strategy is not available if a table has self-referential foreign keys. For example, if the `comments` table has the foreign key `comment_id`.
* You must set `self_referencing = true` in the `dbsync_columns` record.
* The system will automatically fallback to the `Drop & Recreate` strategy for that table.
* If you set the `use_temporal_table` and `self_referencing` fields to `true`, the synchronization will throw an error.

### 2. Forbidden Methods in Columns

In `dbsync_columns`, the method field must only contain data types (_string_, _integer_, etc.).
* Do not use `primary`, `unique`, `index`, or foreign as a `method`.
* Use modifiers for single-column constraints or the `dbsync_tables` fields for composite constraints.

---

## Package tables and their meaning

### `dbsync_connections`

Defines **source and target Laravel connections**.

| Field             | Description                         | Type       | Example  |
|-------------------|-------------------------------------|------------|----------|
| source_connection | Connection name for the origin      | `(string)` | _oracle_ |
| target_connection | Connection name for the destination | `(string)` | _mysql_  |
| active            | Enables or disables this connection | `(bool)`   | _true_   |

---

### `dbsync_tables`

Defines **what to sync and how**.

| Field              | Description                                                                 | Type     | Example                     |
|--------------------|-----------------------------------------------------------------------------|----------|-----------------------------|
| source_table       | Source table name                                                           | (string) | _user_                      |
| target_table       | Destination table name                                                      | (string) | _user_                      |
| min_records        | Minimum number of records required for the sync to be considered successful | (int)    | _1_                         |
| active             | Enables or disables synchronization for this table                          | (bool)   | _true_                      |
| source_query       | Optional custom SELECT                                                      | (string) | _select..._                 |
| use_temporal_table | Enables temporal strategy                                                   | (bool)   | _true_                      |
| batch_size         | Insert chunk size                                                           | (int)    | _500_                       |
| primary_key        | * Primary key definition                                                    | (array)  | `["user_id", "rol_id"]`     |
| unique_keys        | * Unique constraints                                                        | (array)  | `[["name", "type"]]`        |
| indexes            | * Index definitions                                                         | (array)  | `[["name", "description"]]` |
| connection_id      | Reference to the connection used by this table                              | (int)    | _1_                         |


> The `primary_key`, `unique_keys`, and `indexes` fields are only required when using composite keys. Otherwise, they must be defined in the `modifiers` field of the `dbsync_columns` table.
> 
> #### IMPORTANT: The format of these fields is an "array of arrays". Otherwise, the execution will throw an error.

---

### `dbsync_columns`

Defines **table structure using Laravel schema semantics**.

| Field            | Description                                                                                                                             | Type     | Example                                                                                    |
|------------------|-----------------------------------------------------------------------------------------------------------------------------------------|----------|--------------------------------------------------------------------------------------------|
| method           | Blueprint method                                                                                                                        | (string) | _string_, _integer_, _decimal_, _foreignId_, _etc_.                                        |
| parameters       | Method parameters                                                                                                                       | (array)  | `["name", 100]`, `["user_id"]`, _etc_.                                                     |
| modifiers        | Column modifiers                                                                                                                        | (array)  | `["nullable", "unique"]`, `[{"method": "constrained", "parameters": ["user_id"]}]`, _etc_. |
| self_referencing | Indicates whether the foreign key references the table itself. For example, `comment_id` in `comments`.                                 | (bool)   | _true_                                                                                     |
| case_transform   | Indicate whether copying the data will convert it to uppercase or lowercase.                                                            | (string) | _upper_ \| _lower_                                                                         |
| code             | This column does nothing during synchronization. It's only there to help populate the `dbsync_column_table` table with IDs more easily. | (string) | _user1_                                                                                    |

---

### `dbsync_column_table`

Defines the **relationship and ordering between tables and their columns**.

This pivot table determines **which columns belong to each synchronized table and in what order they are created**.

| Field     | Description                                               |
|-----------|-----------------------------------------------------------|
| table_id  | Reference to the synchronized table (`dbsync_tables`)     |
| column_id | Reference to the column definition (`dbsync_columns`)     |
| order     | Position of the column within the table schema definition |

---

## Logs and failure handling

### `dbsync_table_runs`

Every execution is logged. You can monitor:

* **Status:** `running`, `success`, or `failed`
* **Rows copied:** Precise count of processed records.
* **Times:** Start and finish timestamps
* **Error:** Full stack trace and error message in case of failure.

Key behaviors:

* Each table runs independently
* A failure does **not** stop other tables

This makes the process safe for long-running and large imports.

---

## License

laravel-db-sync is open-sourced software licensed under the [GPL-3.0 license](LICENSE).
