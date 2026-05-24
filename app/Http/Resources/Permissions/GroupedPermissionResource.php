<?php
// app/Http/Resources/Permissions/GroupedPermissionResource.php

namespace App\Http\Resources\Permissions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupedPermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => $this['group'],
            'permissions' => PermissionResource::collection($this['permissions']),
            'total' => $this['permissions']->count(),
        ];
    }
}
