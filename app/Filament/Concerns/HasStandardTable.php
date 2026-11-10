<?php

namespace App\Filament\Concerns;

use Filament\Tables\Table;

trait HasStandardTable
{
    public static function configureStandardTable(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100]);
    }
}
