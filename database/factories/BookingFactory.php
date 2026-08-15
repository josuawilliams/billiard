<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = fake()->randomElement(['13:00', '14:00', '15:00', '16:00']);
        $duration = fake()->numberBetween(1, 3);

        return [
            'user_id' => User::factory(),
            'table_id' => Table::factory(),
            'booking_date' => fake()->date(),
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addHours($duration)->format('H:i'),
            'total_price' => fake()->randomFloat(2, 50000, 500000),
            'status' => 'pending',
        ];
    }
}
