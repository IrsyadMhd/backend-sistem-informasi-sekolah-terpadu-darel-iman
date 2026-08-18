<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuranSurah extends Model
{
    use HasFactory;

    protected $table = 'quran_surahs';

    protected $fillable = [
        'nomor',
        'nama',
        'nama_latin',
        'jumlah_ayat',
        'tempat_turun',
        'arti',
        'deskripsi',
        'audio_full',
    ];

    protected $casts = [
        'nomor' => 'integer',
        'jumlah_ayat' => 'integer',
    ];
}
