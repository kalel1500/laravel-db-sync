<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DbsyncConnection extends Model
{
    protected $table = 'dbsync_connections';

    protected $fillable = [
        'source_connection',
        'target_connection',
        'active',
    ];

    public function databases()
    {
        return $this->hasMany(DbsyncDatabase::class, 'connection_id');
    }
}
