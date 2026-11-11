<?php

namespace App\Models\Concerns;

/**
 * Reads `{column}_bn` when the app locale is Bangla, falling back to the base column.
 */
trait HasLocalizedColumns
{
    public function localized(string $column): ?string
    {
        if (app()->getLocale() === 'bn') {
            $translated = $this->getAttribute($column.'_bn');

            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        $value = $this->getAttribute($column);

        return is_string($value) ? $value : null;
    }
}
