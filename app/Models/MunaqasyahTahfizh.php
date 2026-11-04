<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MunaqasyahTahfizh extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'munaqasyah_tahfizhs';

    protected $fillable = [
        'nomor_munaqasyah',
        'nomor_syahadah',
        'student_id',
        'nama_santri',
        'nis',
        'education_unit_id',
        'kelas_id',
        'nama_kelas',
        'juz_diuji',
        'tanggal_ujian',
        'penguji_employee_id',
        'nama_penguji',
        'nilai_tajwid',
        'nilai_fashahah',
        'nilai_kelancaran',
        'nilai_akhir',
        'predikat',
        'status',
        'catatan_dewan_penguji',
        'kurator_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_ujian' => 'date:Y-m-d',
            'nilai_tajwid' => 'integer',
            'nilai_fashahah' => 'integer',
            'nilai_kelancaran' => 'integer',
            'nilai_akhir' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function pengujiEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'penguji_employee_id');
    }

    public function kuratorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurator_user_id');
    }
}
