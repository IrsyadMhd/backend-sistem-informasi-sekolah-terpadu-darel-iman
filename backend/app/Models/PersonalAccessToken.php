<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuidPrimaryKey;
}
