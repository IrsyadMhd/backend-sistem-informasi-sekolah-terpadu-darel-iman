<?php

namespace App\Traits;

use App\Models\MutabaahActivityLog;
use Illuminate\Support\Facades\Auth;

trait HasMutabaahAudit
{
    protected static function bootHasMutabaahAudit(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= Auth::id();
            $model->updated_by ??= Auth::id();
        });
        static::updating(fn ($model) => $model->updated_by = Auth::id());
        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
            $model->saveQuietly();
        });
        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            static::registerModelEvent($event, function ($model) use ($event) {
                MutabaahActivityLog::create([
                    'user_id' => Auth::id(),
                    'subject_type' => $model::class,
                    'subject_id' => $model->getKey(),
                    'event' => $event,
                    'old_values' => $event === 'updated' ? $model->getOriginal() : null,
                    'new_values' => $event === 'deleted' ? null : $model->getAttributes(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            });
        }
    }
}
