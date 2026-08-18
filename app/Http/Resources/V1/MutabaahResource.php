<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutabaahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'trashed' => method_exists($this->resource, 'trashed') && $this->resource->trashed(),
        ];
    }
}
