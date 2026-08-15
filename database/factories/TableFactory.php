<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'name' => 'Table '.fake()->unique()->numberBetween(1, 99),
            'price_per_hour' => fake()->randomFloat(2, 50000, 150000),
        ];
    }
}
