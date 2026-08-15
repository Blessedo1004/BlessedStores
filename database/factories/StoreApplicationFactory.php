<?php

namespace Database\Factories;

use App\Models\StoreApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreApplication>
 */
class StoreApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storeName = $this->faker->company();

        return [
            'owner_name' => $this->faker->name(),
            'store_name' => $storeName,
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'logo' => 'logos/' . $this->faker->lexify('store_logo_????') . '.png',
            'description' => $this->faker->paragraph(),
            // leave status and review fields to DB defaults (pending/null)
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (StoreApplication $application) {
            $count = $this->faker->numberBetween(1, 3);

            $socials = [];

            for ($i = 0; $i < $count; $i++) {
                $socials[] = [
                    'platform' => $this->faker->randomElement(['instagram', 'facebook', 'twitter', 'tiktok', 'linkedin']),
                    'user_name' => $this->faker->userName(),
                ];
            }

            $application->applicationSocials()->createMany($socials);
        });
    }
}
