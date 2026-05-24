<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'mst_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'email_verified',
        'password',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
            'deleted_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * Relasi ke Profile
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    /**
     * Relasi many-to-many dengan Role
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(RoleModel::class, 'model', 'model_has_roles', 'model_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Relasi many-to-many dengan App
     */
    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(MstApp::class, 'user_has_apps', 'user_id', 'app_id')
            ->withPivot(['role_id', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Relasi ke App melalui user_has_apps (dengan pivot)
     */
    public function userApps()
    {
        return $this->hasMany(UserHasApp::class, 'user_id', 'id');
    }

    /**
     * Cek apakah user memiliki role tertentu
     */
    public function hasRole($roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Cek apakah user memiliki permission tertentu
     */
    public function hasPermission($permissionName): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionName) {
            $query->where('name', $permissionName);
        })->exists();
    }

    /**
     * Cek apakah user memiliki akses ke app tertentu
     */
    public function hasAppAccess($appId): bool
    {
        return $this->apps()->where('app_id', $appId)->wherePivot('is_active', true)->exists();
    }

    /**
     * Scope untuk user aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk pencarian
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('profile', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
        }
        return $query;
    }

    // public function hasPermission(string $permission, ?int $appId = null): bool
    // {
    //     $activeAppId = $appId ?? (int) ($this->getAttribute('active_app_id') ?? 0);

    //     if ($activeAppId <= 0) {
    //         return false;
    //     }

    //     return DB::connection('central')
    //         ->table('user_has_apps')
    //         ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'user_has_apps.role_id')
    //         ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
    //         ->where('user_has_apps.user_id', $this->id)
    //         ->where('user_has_apps.app_id', $activeAppId)
    //         ->where('user_has_apps.is_active', true)
    //         ->where('permissions.name', $permission)
    //         ->exists();
    // }
}
