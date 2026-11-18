<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentAttemptStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
