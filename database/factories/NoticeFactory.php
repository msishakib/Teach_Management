<?php

namespace Database\Factories;

use App\Enums\NoticeAudience;
use App\Models\Notice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => NoticeAudience::All,
            'batch_id' => null,
            'published_at' => now(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
