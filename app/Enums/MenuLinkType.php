<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MenuLinkType: string implements HasLabel
{
    case Section = 'section';
    case Page = 'page';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Section => 'Homepage section',
            self::Page => 'Site page',
            self::Custom => 'Custom URL',
        };
    }

    /**
     * Valid targets for the section / page types (value => admin label).
     *
     * @return array<string, string>
     */
    public function targets(): array
    {
        return match ($this) {
            self::Section => [
                'courses' => 'Courses',
                'why' => 'Why us',
                'teachers' => 'Teachers',
                'results' => 'Results',
                'testimonials' => 'Testimonials',
                'notices' => 'Notices',
                'faq' => 'FAQ',
                'contact' => 'Contact',
            ],
            self::Page => [
                'home' => 'Home',
                'apply' => 'Apply / admission',
                'notices.index' => 'All notices',
                'gallery' => 'Gallery',
            ],
            self::Custom => [],
        };
    }
}
