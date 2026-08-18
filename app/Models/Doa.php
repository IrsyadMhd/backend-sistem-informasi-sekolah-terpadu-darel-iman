<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doa extends Model
{
    use HasFactory;

    protected $table = 'doas';

    public $incrementing = false; // Numeric IDs 1-228 set explicitly from EQuran API
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'nama',
        'grup',
        'ar',
        'tr',
        'idn',
        'tentang',
        'tag',
    ];

    protected $casts = [
        'id' => 'integer',
        'tag' => 'array',
    ];
}
