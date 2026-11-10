<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use Illuminate\Database\Seeder;

class GradingScaleSeeder extends Seeder
{
    public function run(): void
    {
        $scale = GradingScale::query()->firstOrCreate(
            ['name' => 'Default GPA Scale'],
            ['is_default' => true],
        );

        if (! $scale->is_default) {
            $scale->update(['is_default' => true]);
        }

        GradingScale::query()
            ->where('id', '!=', $scale->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $rules = [
            ['min_percent' => 80, 'max_percent' => 100, 'letter_grade' => 'A+', 'grade_point' => 5.00, 'remark' => 'Outstanding'],
            ['min_percent' => 70, 'max_percent' => 79.99, 'letter_grade' => 'A', 'grade_point' => 4.00, 'remark' => 'Excellent'],
            ['min_percent' => 60, 'max_percent' => 69.99, 'letter_grade' => 'A-', 'grade_point' => 3.50, 'remark' => 'Very Good'],
            ['min_percent' => 50, 'max_percent' => 59.99, 'letter_grade' => 'B', 'grade_point' => 3.00, 'remark' => 'Good'],
            ['min_percent' => 40, 'max_percent' => 49.99, 'letter_grade' => 'C', 'grade_point' => 2.00, 'remark' => 'Satisfactory'],
            ['min_percent' => 33, 'max_percent' => 39.99, 'letter_grade' => 'D', 'grade_point' => 1.00, 'remark' => 'Pass'],
            ['min_percent' => 0, 'max_percent' => 32.99, 'letter_grade' => 'F', 'grade_point' => 0.00, 'remark' => 'Fail'],
        ];

        foreach ($rules as $rule) {
            $scale->rules()->updateOrCreate(
                [
                    'letter_grade' => $rule['letter_grade'],
                    'min_percent' => $rule['min_percent'],
                ],
                [
                    'max_percent' => $rule['max_percent'],
                    'grade_point' => $rule['grade_point'],
                    'remark' => $rule['remark'],
                ],
            );
        }
    }
}
