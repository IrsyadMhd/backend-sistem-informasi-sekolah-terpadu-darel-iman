<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasUuidPrimaryKey
{
    use HasUuids;

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
