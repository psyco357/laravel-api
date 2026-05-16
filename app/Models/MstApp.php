<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstApp extends CentralModel
{
    protected $table = 'mst_app';

    protected $fillable = [
        'app_code',
        'api_key',
        'connection_id',
        'app_first_name',
        'app_last_name',
        'app_logo',
        'app_version',
        'app_description',
        'app_author',
        'app_license',
        'app_favicon',
        'app_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'connection_id' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_apps', 'app_id', 'user_id')
            ->withPivot(['role_id', 'is_active'])
            ->withTimestamps();
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(MenuModel::class, 'menu_item_app', 'app_id', 'menu_id')
            ->withTimestamps();
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MstConnection::class, 'connection_id');
    }
}
