<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncTableRun extends Model
{
    protected $table = 'dbsync_table_runs';

    protected $fillable = [
        'connection_id',
        'database_id',
        'table_id',
        'status',
        'started_at',
        'finished_at',
        'rows_copied',
        'error_message',
        'error_trace',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}

