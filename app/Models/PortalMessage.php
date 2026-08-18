<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalMessage extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['student_id', 'sender_user_id', 'recipient_user_id', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_user_id'); }
    public function student() { return $this->belongsTo(Student::class); }
}
