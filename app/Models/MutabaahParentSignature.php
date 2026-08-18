<?php

namespace App\Models;

use App\Enums\Mutabaah\SignatureStatus;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahParentSignature extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['daily_header_id', 'parent_user_id', 'signature_status', 'comment', 'signed_at', 'device_info', 'ip_address'];

    protected function casts(): array
    {
        return ['signature_status' => SignatureStatus::class, 'signed_at' => 'datetime', 'device_info' => 'array'];
    }

    public function dailyHeader()
    {
        return $this->belongsTo(MutabaahDailyHeader::class);
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereHas('dailyHeader', fn ($q) => $q->byDate($date));
    }
}
