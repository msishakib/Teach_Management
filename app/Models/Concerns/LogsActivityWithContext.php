<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Adds an audit trail to a model and records the request IP/user agent
 * alongside the old/new attribute values.
 */
trait LogsActivityWithContext
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept($this->activityLogExcept())
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogName(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return [
            'password',
            'remember_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    protected function activityLogDescription(string $eventName): string
    {
        return class_basename($this).' '.$eventName;
    }
}
