<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', 'now');
        $end = (clone $start)->modify('+'.fake()->numberBetween(60, 180).' days');

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->paragraphs(2, true),
            'banner_path' => null,
            'price_cents' => fake()->numberBetween(5_000, 80_000),
            'currency' => 'brl',
            'active' => true,
            'enrollment_starts_at' => $start->format('Y-m-d'),
            'enrollment_ends_at' => $end->format('Y-m-d'),
            'max_seats' => fake()->optional(0.7)->numberBetween(10, 200),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
