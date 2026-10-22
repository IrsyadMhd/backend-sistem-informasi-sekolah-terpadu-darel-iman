<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsMateriSupervisiLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lms_materi_supervisi_logs';

    public $timestamps = false;

    protected $fillable = [
        'materi_id',
        'user_id',
        'user_name',
        'user_role',
        'status_sebelumnya',
        'status_baru',
        'catatan',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(LmsMateri::class, 'materi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
