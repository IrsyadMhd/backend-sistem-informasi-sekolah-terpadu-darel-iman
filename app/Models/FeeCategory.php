<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeCategory extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'is_recurring',
        'default_amount',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'default_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function bills()
    {
        return $this->hasMany(StudentBill::class, 'fee_category_id');
    }

    // Scopes
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }
}
