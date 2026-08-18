<?php

namespace App\Models;

use App\Enums\Mutabaah\InputType;
use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahAgendaItem extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'category_id', 'code', 'name', 'input_type', 'weight', 'sort_order',
        'icon', 'color', 'description', 'is_active',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'input_type' => InputType::class,
            'weight' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(MutabaahCategory::class);
    }

    public function templateItems()
    {
        return $this->hasMany(MutabaahTemplateItem::class, 'agenda_item_id');
    }

    public function templates()
    {
        return $this->belongsToMany(MutabaahTemplate::class, 'mutabaah_template_items', 'agenda_item_id', 'template_id')->withPivot(['sort_order', 'weight', 'target_value', 'is_required', 'requires_parent_signature', 'instruction', 'is_active'])->withTimestamps();
    }

    public function dailyDetails()
    {
        return $this->hasMany(MutabaahDailyDetail::class, 'agenda_item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
