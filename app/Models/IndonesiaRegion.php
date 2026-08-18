<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndonesiaRegion extends Model
{
    use HasFactory;

    protected $table = 'indonesia_regions';

    protected $fillable = [
        'provinsi',
        'kota_kabupaten',
        'kecamatan',
        'kelurahan',
    ];
}
