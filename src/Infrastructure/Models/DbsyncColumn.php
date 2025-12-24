<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncColumn extends Model
{
    protected $table = 'dbsync_columns';

    protected $fillable = [
        'method',
        'parameters',
        'modifiers',
    ];

    protected $casts = [
        'parameters' => 'array',
        'modifiers'  => 'array',
    ];
}
