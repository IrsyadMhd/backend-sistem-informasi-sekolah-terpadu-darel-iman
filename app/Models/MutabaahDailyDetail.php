<?php

namespace App\Models;

use App\Enums\Mutabaah\DetailStatus;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahDailyDetail extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['daily_header_id', 'template_item_id', 'agenda_item_id', 'status_value', 'numeric_value', 'text_value', 'notes', 'input_by', 'input_at'];

    protected function casts(): array
    {
        return ['status_value' => DetailStatus::class, 'numeric_value' => 'decimal:2', 'input_at' => 'datetime'];
    }

    public function dailyHeader()
    {
        return $this->belongsTo(MutabaahDailyHeader::class);
    }

    public function templateItem()
    {
        return $this->belongsTo(MutabaahTemplateItem::class);
    }

    public function agendaItem()
    {
        return $this->belongsTo(MutabaahAgendaItem::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereHas('dailyHeader', fn ($q) => $q->byDate($date));
    }

    public function scopeByRombel($query, string $id)
    {
        return $query->whereHas('dailyHeader', fn ($q) => $q->byRombel($id));
    }

    public function scopeBySupervisor($query, string $id)
    {
        return $query->whereHas('dailyHeader', fn ($q) => $q->bySupervisor($id));
    }
}
