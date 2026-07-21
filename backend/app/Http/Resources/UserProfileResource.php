<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'metadata' => $this->metadata,
        ];
    }
}
