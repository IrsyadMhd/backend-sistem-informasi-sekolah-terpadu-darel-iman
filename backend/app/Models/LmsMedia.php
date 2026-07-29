<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class LmsMedia extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lms_media';

    protected $fillable = [
        'materi_id',
        'nama_file',
        'tipe_file',
        'path_file',
        'url_eksternal',
        'ukuran_bytes',
        'durasi_detik',
        'deskripsi',
        'urutan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ukuran_bytes' => 'integer',
            'durasi_detik' => 'integer',
            'urutan' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(LmsMateri::class, 'materi_id');
    }
}
