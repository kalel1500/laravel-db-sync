<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncDatabase extends Model
{
    protected $table = 'dbsync_databases';

    protected $guarded = [];

    public function connection()
    {
        return $this->belongsTo(DbsyncConnection::class);
    }

    public function tables()
    {
        return $this->hasMany(DbsyncTable::class, 'database_id');
    }
}
