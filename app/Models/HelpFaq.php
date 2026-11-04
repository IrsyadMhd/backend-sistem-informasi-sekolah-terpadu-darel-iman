<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpFaq extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'help_faqs';

    protected $fillable = [
        'category',
        'question',
        'answer',
        'tags',
        'action_url',
        'action_label',
        'sort_order',
        'is_active',
        'helpful_votes',
        'unhelpful_votes',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'helpful_votes' => 'integer',
            'unhelpful_votes' => 'integer',
        ];
    }
}
