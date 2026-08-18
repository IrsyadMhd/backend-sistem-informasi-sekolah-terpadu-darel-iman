<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'bill_id',
        'invoice_number',
        'payment_method',
        'paid_amount',
        'paid_at',
        'payment_gateway_payload',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_gateway_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function bill()
    {
        return $this->belongsTo(StudentBill::class, 'bill_id');
    }

    // Scopes
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }
}
