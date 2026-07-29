<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsDiskusiKomentarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'diskusi_id' => $this->diskusi_id,
            'parent_id' => $this->parent_id,
            'user_id' => $this->user_id,
            'nama_pengirim' => $this->user ? $this->user->name : ($this->creator ? $this->creator->name : 'Pengguna'),
            'email_pengirim' => $this->user ? $this->user->email : null,
            'peran_pengirim' => $this->peran_pengirim ?? 'Guru',
            'konten' => $this->konten,
            'is_solution' => (bool) $this->is_solution,
            'replies' => LmsDiskusiKomentarResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->replies ? $this->replies->count() : 0,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'created_at_formatted' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
