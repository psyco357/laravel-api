<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuModel extends CentralModel
{
    protected $table = 'menu';
    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'icon',
        'path',
        'badge_key',
        'step',
        'sort_order',
        'is_active',
    ];
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subItems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        // 🔥 Yang PENTING: tambahkan with('children') untuk recursive auto-load
        return $this->hasMany(self::class, 'parent_id')
            ->with('children')  // ← Tambahkan ini!
            ->orderBy('sort_order', 'asc');
    }

    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(MstApp::class, 'menu_item_app', 'menu_id', 'app_id')
            ->withTimestamps();
    }
}
