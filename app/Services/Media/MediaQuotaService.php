<?php

namespace App\Services\Media;

use App\Enums\MediaCategory;
use App\Models\Media;
use Illuminate\Validation\ValidationException;

class MediaQuotaService
{
    /**
     * Bytes currently stored for a category.
     */
    public function usageBytes(MediaCategory $category): int
    {
        return (int) Media::query()
            ->where('collection_name', $category->value)
            ->sum('size');
    }

    public function usageMb(MediaCategory $category): float
    {
        return round($this->usageBytes($category) / 1048576, 2);
    }

    public function quotaBytes(MediaCategory $category): int
    {
        return $category->quotaBytes();
    }

    public function hasQuota(MediaCategory $category): bool
    {
        return $this->quotaBytes($category) > 0;
    }

    public function remainingBytes(MediaCategory $category): ?int
    {
        if (! $this->hasQuota($category)) {
            return null;
        }

        return max(0, $this->quotaBytes($category) - $this->usageBytes($category));
    }

    public function percentUsed(MediaCategory $category): ?float
    {
        $quota = $this->quotaBytes($category);

        if ($quota <= 0) {
            return null;
        }

        return round(min(100, ($this->usageBytes($category) / $quota) * 100), 1);
    }

    /**
     * Guard an incoming upload against the category quota.
     */
    public function assertCanStore(MediaCategory $category, int $incomingBytes): void
    {
        if (! $this->hasQuota($category)) {
            return;
        }

        if ($this->usageBytes($category) + $incomingBytes > $this->quotaBytes($category)) {
            throw ValidationException::withMessages([
                'file' => "The {$category->label()} storage quota has been reached.",
            ]);
        }
    }

    /**
     * @return array<string, array{label: string, usage_bytes: int, quota_bytes: int, percent: float|null}>
     */
    public function summary(): array
    {
        $summary = [];

        foreach (MediaCategory::cases() as $category) {
            $summary[$category->value] = [
                'label' => $category->label(),
                'usage_bytes' => $this->usageBytes($category),
                'quota_bytes' => $this->quotaBytes($category),
                'percent' => $this->percentUsed($category),
            ];
        }

        return $summary;
    }

    public function totalUsageBytes(): int
    {
        return (int) Media::query()->sum('size');
    }
}
