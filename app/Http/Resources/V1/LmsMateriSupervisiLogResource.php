<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsMateriSupervisiLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'materi_id' => $this->materi_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'user_role' => $this->user_role,
            'status_sebelumnya' => $this->status_sebelumnya,
            'status_baru' => $this->status_baru,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
