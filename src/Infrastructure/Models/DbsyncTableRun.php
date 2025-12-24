<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncTableRun extends Model
{
    protected $table = 'dbsync_table_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}

