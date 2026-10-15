<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HazardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['pothole', 'fallen_tree', 'flooding', 'broken_traffic_light', 'road_damage', 'landslide', 'manhole_cover_missing', 'road_accident']),
            'description' => fake()->sentence(10),
            'lat' => fake()->latitude(14.5, 14.65),
            'lng' => fake()->longitude(120.95, 121.05),
            'image_path' => null,
            'status' => fake()->randomElement(['pending', 'confirmed', 'resolved']),
            'user_id' => User::factory(),
        ];
    }
}
