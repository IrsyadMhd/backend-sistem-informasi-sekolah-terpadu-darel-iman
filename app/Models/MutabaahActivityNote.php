<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahActivityNote extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['daily_header_id', 'user_id', 'note_type', 'note'];

    public function dailyHeader()
    {
        return $this->belongsTo(MutabaahDailyHeader::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereHas('dailyHeader', fn ($q) => $q->byDate($date));
    }
}
