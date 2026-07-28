<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModulSemester extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'modul_semesters';

    protected $fillable = [
        'tahun_ajaran_id',
        'semester_id',
        'unit_pendidikan_id',
        'kelas_id',
        'mata_pelajaran_id',
        'guru_id',
        'kode_modul',
        'nama_modul',
        'jenjang',
        'kurikulum',
        'status',
        'atp',
        'cp',
        'tujuan_pembelajaran',
        'alokasi_jam',
        'jumlah_pertemuan',
        'metode_pembelajaran',
        'model_pembelajaran',
        'media_pembelajaran',
        'sumber_belajar',
        'target_nilai_minimum',
        'target_kehadiran',
        'target_hafalan',
        'target_proyek',
        'berlaku_mulai',
        'berlaku_sampai',
        'ditampilkan_di_portal_ortu',
        'ditampilkan_di_aplikasi_siswa',
        'arsip_otomatis',
        'bobot_tugas',
        'bobot_quiz',
        'bobot_projek',
        'bobot_uts',
        'bobot_uas',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'alokasi_jam' => 'integer',
        'jumlah_pertemuan' => 'integer',
        'target_nilai_minimum' => 'float',
        'target_kehadiran' => 'float',
        'ditampilkan_di_portal_ortu' => 'boolean',
        'ditampilkan_di_aplikasi_siswa' => 'boolean',
        'arsip_otomatis' => 'boolean',
        'bobot_tugas' => 'float',
        'bobot_quiz' => 'float',
        'bobot_projek' => 'float',
        'bobot_uts' => 'float',
        'bobot_uas' => 'float',
        'berlaku_mulai' => 'date:Y-m-d',
        'berlaku_sampai' => 'date:Y-m-d',
    ];

    public function tahunAjaran()
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function unitPendidikan()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function guru()
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function details()
    {
        return $this->hasMany(ModulSemesterDetail::class, 'modul_semester_id')->orderBy('minggu', 'asc');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
