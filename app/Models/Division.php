<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'divisions';

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // --- Relations ---

    /** Divisi induk (self-referential) */
    public function parent()
    {
        return $this->belongsTo(Division::class, 'parent_id');
    }

    /** Sub-divisi (anak) */
    public function children()
    {
        return $this->hasMany(Division::class, 'parent_id');
    }

    /** Pegawai yang berada di divisi ini */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'division_id');
    }

    /** Data pemantauan yang terkait divisi ini */
    public function pemantauan()
    {
        return $this->hasMany(PemantauanDivisi::class, 'division_id');
    }

    // --- Scopes ---

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootOnly($query)
    {
        return $query->whereNull('parent_id');
    }
}
