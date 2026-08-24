<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMedicalHistory extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'student_medical_histories';

    protected $fillable = [
        'student_id',
        'congenital_diseases',
        'allergies',
        'emergency_contact_phone',
        'special_instructions',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
