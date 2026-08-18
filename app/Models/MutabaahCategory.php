<?php

namespace App\Models;

use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahCategory extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'icon', 'color', 'sort_order', 'description',
        'is_active', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function agendaItems()
    {
        return $this->hasMany(MutabaahAgendaItem::class, 'category_id');
    }

    public function legacyAgendas()
    {
        return $this->hasMany(MutabaahAgenda::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
