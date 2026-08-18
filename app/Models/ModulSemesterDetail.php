<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulSemesterDetail extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'modul_semester_details';

    protected $fillable = [
        'modul_semester_id',
        'minggu',
        'materi',
        'atp',
        'cp',
        'jp',
        'keterangan',
    ];

    protected $casts = [
        'minggu' => 'integer',
        'jp' => 'integer',
    ];

    public function modulSemester()
    {
        return $this->belongsTo(ModulSemester::class, 'modul_semester_id');
    }
}
