<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisiKbm extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'supervisi_kbms';

    protected $fillable = [
        'guru_id',
        'unit_id',
        'mapel',
        'kelas',
        'tanggal',
        'supervisor',
        'status',
        'skor_total',
        'rubrik',
        'apresiasi',
        'rekomendasi',
        'id_penginput',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'skor_total' => 'integer',
            'rubrik' => 'array',
            'data_tambahan' => 'array',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function penginput(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penginput');
    }
}
