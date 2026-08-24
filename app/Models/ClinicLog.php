<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicLog extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'clinic_logs';

    protected $fillable = [
        'student_id',
        'handled_by_musyrif_id',
        'symptom_start_at',
        'symptoms',
        'medicine_given',
        'rest_recommendation',
        'status',
    ];

    protected $casts = [
        'symptom_start_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by_musyrif_id');
    }
}
