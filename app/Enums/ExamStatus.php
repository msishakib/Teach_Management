<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExamStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case MarksEntry = 'marks_entry';
    case Published = 'published';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Ongoing => 'Ongoing',
            self::MarksEntry => 'Marks entry',
            self::Published => 'Published',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Ongoing => 'warning',
            self::MarksEntry => 'primary',
            self::Published => 'success',
            self::Closed => 'danger',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled],
            self::Scheduled => [self::Ongoing, self::Draft],
            self::Ongoing => [self::MarksEntry],
            self::MarksEntry => [self::Published],
            self::Published => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
