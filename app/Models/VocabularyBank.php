<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VocabularyBank extends Model
{
    use HasFactory;

    protected $table = 'vocabulary_banks';

    protected $fillable = [
        'unit_id',
        'subject_id',
        'language',
        'word',
        'phonetic',
        'translation',
        'example_sentence',
        'target_level',
        'theme',
        'created_by',
        'status',
        'principal_id',
        'principal_approved_at',
        'principal_notes',
        'divisi_id',
        'divisi_approved_at',
        'divisi_notes',
    ];

    protected function casts(): array
    {
        return [
            'principal_approved_at' => 'datetime',
            'divisi_approved_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divisi_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(StudentVocabularySubmission::class, 'vocabulary_id');
    }
}
