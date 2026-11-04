<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KurasiSoal extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'kurasi_soals';

    protected $fillable = [
        'kode_kurasi',
        'judul',
        'education_unit_id',
        'subject_id',
        'penyusun_employee_id',
        'nama_penyusun',
        'nama_mapel',
        'jenjang',
        'klasifikasi',
        'tingkat_kesukaran',
        'status',
        'tanggal_reviu',
        'kurator_user_id',
        'nama_kurator',
        'catatan_kurator',
        'rubrik_kurasi',
        'lampiran_dokumen_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_reviu' => 'date:Y-m-d',
            'rubrik_kurasi' => 'array',
            'metadata' => 'array',
        ];
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function penyusunEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'penyusun_employee_id');
    }

    public function kuratorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurator_user_id');
    }
}
