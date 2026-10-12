<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MutabaahInputRule extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['assessment_period_id', 'agenda_item_id', 'program_type', 'input_source', 'location', 'weekdays', 'school_day_only', 'requires_verification', 'priority', 'is_active', 'metadata', 'created_by', 'updated_by'];
    protected $casts = ['weekdays' => 'array', 'school_day_only' => 'boolean', 'requires_verification' => 'boolean', 'priority' => 'integer', 'is_active' => 'boolean', 'metadata' => 'array'];

    public function period() { return $this->belongsTo(MutabaahAssessmentPeriod::class, 'assessment_period_id'); }
    public function agendaItem() { return $this->belongsTo(MutabaahAgendaItem::class); }

    public static function invalidateRuleCaches(?string $programType = null): void
    {
        $programs = array_unique(array_filter([
            $programType,
            'fullday',
            'boarding',
            'regular',
            'tahfizh',
        ]));

        $flush = function () use ($programs) {
            foreach ($programs as $prog) {
                Cache::forget("mutabaah_input_rules_{$prog}");
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($flush);
        } else {
            $flush();
        }
    }

    public static function bulkUpdateRules(array $ids, array $attributes): int
    {
        $updated = static::query()->whereIn('id', $ids)->update($attributes);
        static::invalidateRuleCaches();
        return $updated;
    }

    protected static function booted(): void
    {
        $clear = function (MutabaahInputRule $rule) {
            static::invalidateRuleCaches($rule->program_type);
        };

        static::saved($clear);
        static::deleted($clear);
        static::restored($clear);
    }
}

