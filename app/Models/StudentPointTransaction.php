<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPointTransaction extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'student_point_transactions';

    protected $fillable = [
        'student_id',
        'category_id',
        'reported_by_id',
        'points',
        'transaction_date',
        'description',
        'status',
    ];

    protected $casts = [
        'points' => 'integer',
        'transaction_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function category()
    {
        return $this->belongsTo(DisciplineCategory::class, 'category_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }
}
