# Database Sync for Laravel

<p align="center">
    <!-- <a href="https://github.com/kalel1500/laravel-db-sync/actions/workflows/tests.yml"><img src="https://github.com/kalel1500/laravel-db-sync/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a> -->
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/dt/kalel1500/laravel-db-sync" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/v/kalel1500/laravel-db-sync" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/kalel1500/laravel-db-sync" target="_blank"><img src="https://img.shields.io/packagist/l/kalel1500/laravel-db-sync" alt="License"></a>
</p>

A Laravel package to **safely synchronize tables and data from external databases** into your application database.

This package is designed for scenarios where you need to **pull data from other machines or systems** (MySQL, PostgreSQL, Oracle, etc.) into your Laravel application in a controlled, traceable, and production-ready way, without forcing your final domain schema to match the source.

---

## What this package does

* Copies full tables from external databases into your application database
* Creates target tables automatically based on database-defined metadata
* Supports large tables and long-running syncs
* Tracks execution status, progress, and errors
* Works across multiple database engines
* Can be executed via Artisan commands or queued jobs

This package focuses on **data ingestion**, not on how that data is later processed inside your application.

---

## Typical use cases

* Importing data from legacy systems
* Synchronizing data between different servers
* Periodic ingestion from external databases
* Creating staging tables for later processing
* Centralizing data from multiple sources

---

## Key features

### Multi-database support

You can define multiple external connections and synchronize data from different database engines into a single Laravel application.

---

### Table-level synchronization strategies

Each table can define **how it should be synchronized**, depending on its size and availability requirements.

#### Drop & Recreate

* Drops the target table
* Recreates it
* Inserts all data

**Pros**

* Simple and fast

**Cons**

* Data is temporarily unavailable during the sync

---

#### Temporal Table (recommended for large tables)

* Creates a temporary table
* Loads all data into the temporary table
* Drops the original table
* Renames the temporary table

**Pros**

* Minimizes data unavailability
* Safer for large tables

Enable it per table:

```text
use_temporal_table = true
```

---

### Database-driven schema definition

Table structures are **defined in the database**, not in Laravel migrations.

This allows:

* Dynamic schemas
* No deployment needed for schema changes
* Centralized control of external table definitions

You can define:

* Columns
* Primary keys (including composite keys)
* Unique constraints
* Indexes

---

### Flexible column definitions

Columns are defined using:

* a column type
* a list of parameters
* optional modifiers

This mirrors Laravel’s schema builder, but stored as data.

Example (conceptual):

```json
{
  "method": "string",
  "parameters": ["email", 255],
  "modifiers": ["nullable", "unique"]
}
```

This approach is expressive enough for real-world schemas without being overly complex.

---

### Execution tracking and logging

Every table sync creates a run record that tracks:

* start and finish time
* current status (`running`, `success`, `failed`)
* number of rows copied
* error message and stack trace (if any)

This makes it easy to:

* monitor progress
* detect failures
* build dashboards or admin views
* retry failed tables

---

### Failure isolation

* Each table is processed independently
* If one table fails, the rest continue
* Errors are captured and stored
* No global rollback assumptions are made

This is especially important for:

* large datasets
* heterogeneous database engines
* long-running sync jobs

---

## How it fits into your application

A common flow looks like this:

1. Define external connections, databases, and tables
2. Define the table schema metadata
3. Run a command or dispatch a job
4. Data is synchronized into staging or target tables
5. Your application processes the data as needed

The package **does not impose** how you should use the data afterwards.

---

## License

GPL-3.0 license

---

## Final note

This package is not a replacement for migrations or ORMs.

It solves a **specific problem**:
bringing data from external databases into your Laravel application in a way that is safe, observable, and scalable.

If that is your use case, this package is built exactly for that purpose.
