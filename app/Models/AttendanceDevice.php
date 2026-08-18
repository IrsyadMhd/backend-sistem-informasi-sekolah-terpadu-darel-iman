<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['device_code', 'device_name', 'device_type', 'vendor', 'unit_id', 'location', 'api_key_hash', 'status', 'last_seen_at', 'configuration'];

    protected $hidden = ['api_key_hash'];

    protected $casts = ['last_seen_at' => 'datetime', 'configuration' => 'array'];
}
