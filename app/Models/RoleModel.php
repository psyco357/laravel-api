<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class RoleModel extends CentralModel
{
    use HasFactory;

    protected $table = 'role';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionModel::class, 'role_has_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }


    /**
     * Relasi many-to-many dengan User (model_has_roles)
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id')
            ->withTimestamps();
    }

    /**
     * Relasi many-to-many dengan Menu
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(MenuModel::class, 'menu_item_role', 'role_id', 'menu_id')
            ->withTimestamps();
    }

    /**
     * Scope untuk pencarian
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('name', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }
        return $query;
    }

    /**
     * Scope untuk filter aktif (jika ada is_active field nanti)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
