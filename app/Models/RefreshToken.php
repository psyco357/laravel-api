<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends CentralModel
{
    protected $table = 'auth_refresh_tokens';

    protected $fillable = [
        'session_id',
        'user_id',
        'app_id',
        'token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MstApp::class, 'app_id');
    }
}
