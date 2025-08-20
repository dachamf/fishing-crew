<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->name(),
            'birth_year' => $this->faker->randomNumber(),
            'location' => $this->faker->word(),
            'favorite_species' => $this->faker->word(),
            'gear' => $this->faker->word(),
            'bio' => $this->faker->word(),
            'avatar_path' => $this->faker->word(),
            'settings' => $this->faker->words(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
        ];
    }
}
