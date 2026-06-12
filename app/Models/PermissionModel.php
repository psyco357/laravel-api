<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\RoleModel;

class PermissionModel extends CentralModel
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'display_name',
        'group',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi many-to-many dengan Role
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleModel::class, 'role_has_permissions', 'permission_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Scope untuk filter berdasarkan group
     */
    public function scopeByGroup($query, $group)
    {
        if ($group) {
            return $query->where('group', $group);
        }
        return $query;
    }

    /**
     * Scope untuk pencarian
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('name', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%");
        }
        return $query;
    }

    /**
     * Mendapatkan semua group yang tersedia
     */
    public static function getGroups()
    {
        return self::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->toArray();
    }
}
