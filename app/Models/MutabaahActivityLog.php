<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MutabaahActivityLog extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'event', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
