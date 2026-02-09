<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncColumn extends Model
{
    protected $table = 'dbsync_columns';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'modifiers'  => 'array',
    ];

    public function tables()
    {
        return $this->belongsToMany(DbsyncTable::class, 'dbsync_column_table','column_id', 'table_id')
            ->withPivot('order')
            ->orderBy('dbsync_column_table.order');
    }
}
