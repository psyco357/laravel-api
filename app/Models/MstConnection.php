<?php

namespace App\Models;

class MstConnection extends CentralModel
{
    protected $table = 'mst_connection';

    protected $fillable = [
        'name',
        'host',
        'db_name',
        'port',
        'username',
        'password',
        'driver',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
