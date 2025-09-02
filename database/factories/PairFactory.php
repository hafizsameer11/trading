<?php

namespace Database\Factories;

use App\Models\Pair;
use Illuminate\Database\Eloquent\Factories\Factory;

class PairFactory extends Factory
{
    protected $model = Pair::class;

    public function definition(): array
    {
        $symbols = ['EUR/USD', 'GBP/USD', 'USD/JPY', 'AUD/USD', 'USD/CAD'];
        $symbol = $this->faker->randomElement($symbols);
        $slug = str_replace('/', '-', $symbol);

        return [
            'symbol' => $symbol,
            'slug' => $slug,
            'type' => $this->faker->randomElement(['LIVE', 'OTC']),
            'is_active' => true,
            'base_currency' => explode('/', $symbol)[0],
            'quote_currency' => explode('/', $symbol)[1],
            'trend_mode' => $this->faker->randomElement(['UP', 'DOWN', 'SIDEWAYS']),
            'volatility' => $this->faker->randomElement(['LOW', 'MID', 'HIGH']),
            'min_price' => $this->faker->randomFloat(8, 0.5, 2.0),
            'max_price' => $this->faker->randomFloat(8, 2.0, 5.0),
            'price_precision' => 5,
            'meta' => [
                'anchor' => $this->faker->randomFloat(4, 0.8, 1.5),
            ],
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'LIVE',
        ]);
    }

    public function otc(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'OTC',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

