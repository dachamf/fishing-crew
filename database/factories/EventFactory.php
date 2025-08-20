<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['draft', 'scheduled', 'postponed', 'done']);

        return [
            'group_id' => Group::factory(),
            'title' => $this->faker->sentence(3),
            'location_name' => $this->faker->optional()->city(),
            'latitude' => $this->faker->optional()->randomFloat(6, -90, 90),
            'longitude' => $this->faker->optional()->randomFloat(6, -180, 180),
            'start_at' => $this->faker->dateTimeBetween('+1 day', '+2 months'),
            'description' => $this->faker->optional()->paragraph(),
            'status' => $status,

        ];
    }
}
