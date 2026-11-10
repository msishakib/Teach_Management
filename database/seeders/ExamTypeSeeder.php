<?php

namespace Database\Seeders;

use App\Enums\ExamTypeStatus;
use App\Models\ExamType;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Class Test', 'weight' => 10],
            ['name' => 'Monthly', 'weight' => 15],
            ['name' => 'Midterm', 'weight' => 30],
            ['name' => 'Final', 'weight' => 45],
        ];

        foreach ($types as $type) {
            ExamType::query()->updateOrCreate(
                ['name' => $type['name']],
                [
                    'weight' => $type['weight'],
                    'status' => ExamTypeStatus::Active,
                ],
            );
        }
    }
}
