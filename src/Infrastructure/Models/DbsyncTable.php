<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncTable extends Model
{
    protected $table = 'dbsync_tables';

    protected $guarded = [];

    protected $casts = [
        'primary_key' => 'array',
        'unique_keys' => 'array',
        'indexes'     => 'array',
    ];

    public function database()
    {
        return $this->belongsTo(DbsyncDatabase::class, 'database_id');
    }

    public function columns()
    {
        return $this->belongsToMany(DbsyncColumn::class, 'dbsync_column_table', 'table_id', 'column_id')
            ->withPivot('order')
            ->orderBy('dbsync_column_table.order');
    }
}
