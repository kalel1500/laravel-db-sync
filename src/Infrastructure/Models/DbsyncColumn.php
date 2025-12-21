<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncColumn extends Model
{
    protected $table = 'dbsync_columns';

    protected $fillable = [
        'name',
        'method',
        'parameters',
        'modifiers',
        'is_primary',
    ];

    protected $casts = [
        'parameters' => 'array',
        'modifiers' => 'array',
        'is_primary' => 'boolean',
    ];
}
