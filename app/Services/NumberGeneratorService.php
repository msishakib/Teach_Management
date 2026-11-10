<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function next(NumberSequenceType $type, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($type, $year) {
            $sequencesSetting = $this->lockOrCreateSetting(
                $type->settingGroup(),
                $type->sequencesSettingName(),
                [],
            );

            $prefixSetting = $this->lockOrCreateSetting(
                $type->settingGroup(),
                $type->prefixSettingName(),
                (string) data_get(config('settings.defaults'), $type->prefixSettingKey(), strtoupper($type->value)),
            );

            $formatSetting = $this->lockOrCreateSetting(
                'numbering',
                'format',
                (string) data_get(config('settings.defaults'), 'numbering.format', '{PREFIX}-{YEAR}-{NUMBER}'),
            );

            $paddingSetting = $this->lockOrCreateSetting(
                'numbering',
                'padding',
                (int) data_get(config('settings.defaults'), 'numbering.padding', 4),
            );

            $sequences = is_array($sequencesSetting->value) ? $sequencesSetting->value : [];
            $yearKey = (string) $year;
            $next = ((int) ($sequences[$yearKey] ?? 0)) + 1;
            $sequences[$yearKey] = $next;
            $sequencesSetting->update(['value' => $sequences]);

            $prefix = is_string($prefixSetting->value) && $prefixSetting->value !== ''
                ? $prefixSetting->value
                : strtoupper($type->value);

            $format = is_string($formatSetting->value) && $formatSetting->value !== ''
                ? $formatSetting->value
                : '{PREFIX}-{YEAR}-{NUMBER}';

            $padding = max(1, (int) $paddingSetting->value);

            $this->settings->forgetCache();

            return strtr($format, [
                '{PREFIX}' => $prefix,
                '{YEAR}' => (string) $year,
                '{NUMBER}' => str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function nextAdmission(?int $year = null): string
    {
        return $this->next(NumberSequenceType::Admission, $year);
    }

    public function nextEmployee(?int $year = null): string
    {
        return $this->next(NumberSequenceType::Employee, $year);
    }

    public function nextInvoice(?int $year = null): string
    {
        return $this->next(NumberSequenceType::Invoice, $year);
    }

    public function nextFeeReceipt(?int $year = null): string
    {
        return $this->next(NumberSequenceType::FeeReceipt, $year);
    }

    public function nextPaymentVoucher(?int $year = null): string
    {
        return $this->next(NumberSequenceType::PaymentVoucher, $year);
    }

    public function nextReceiveVoucher(?int $year = null): string
    {
        return $this->next(NumberSequenceType::ReceiveVoucher, $year);
    }

    protected function lockOrCreateSetting(string $group, string $key, mixed $default): Setting
    {
        $setting = Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if ($setting !== null) {
            return $setting;
        }

        Setting::query()->create([
            'group' => $group,
            'key' => $key,
            'value' => $default,
        ]);

        return Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
