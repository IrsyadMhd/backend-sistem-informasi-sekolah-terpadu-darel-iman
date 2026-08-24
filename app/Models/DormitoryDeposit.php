<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DormitoryDeposit extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'dormitory_deposits';

    protected $fillable = [
        'student_id',
        'musyrif_id',
        'item_type',
        'item_name',
        'serial_number',
        'deposited_at',
        'retrieved_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'deposited_at' => 'datetime',
        'retrieved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function musyrif()
    {
        return $this->belongsTo(User::class, 'musyrif_id');
    }
}
