<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpTicket extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'help_tickets';

    protected $fillable = [
        'ticket_number',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'category',
        'priority',
        'subject',
        'description',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
