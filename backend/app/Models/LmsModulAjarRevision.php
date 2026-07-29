<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsModulAjarRevision extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lms_modul_ajar_revisions';

    public $timestamps = false;

    protected $fillable = [
        'modul_ajar_id',
        'versi',
        'judul_modul',
        'catatan_revisi',
        'snapshot_data',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function modulAjar(): BelongsTo
    {
        return $this->belongsTo(LmsModulAjar::class, 'modul_ajar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
