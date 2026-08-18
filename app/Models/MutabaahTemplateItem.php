<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahTemplateItem extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['template_id', 'agenda_item_id', 'sort_order', 'weight', 'target_value', 'is_required', 'requires_parent_signature', 'instruction', 'is_active', 'agenda_id', 'target'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_required' => 'boolean', 'requires_parent_signature' => 'boolean', 'is_active' => 'boolean', 'weight' => 'decimal:2', 'target_value' => 'decimal:2', 'target' => 'decimal:2'];
    }

    public function template()
    {
        return $this->belongsTo(MutabaahTemplate::class);
    }

    public function agendaItem()
    {
        return $this->belongsTo(MutabaahAgendaItem::class, 'agenda_item_id');
    }

    public function legacyAgenda()
    {
        return $this->belongsTo(MutabaahAgenda::class, 'agenda_id');
    }

    public function dailyDetails()
    {
        return $this->hasMany(MutabaahDailyDetail::class, 'template_item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
