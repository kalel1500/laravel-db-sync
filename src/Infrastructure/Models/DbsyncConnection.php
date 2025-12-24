<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncConnection extends Model
{
    protected $table = 'dbsync_connections';

    protected $guarded = [];

    public function databases()
    {
        return $this->hasMany(DbsyncDatabase::class, 'connection_id');
    }
}
