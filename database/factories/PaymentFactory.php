<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'payment_gateway' => 'mock',
            'transaction_id' => 'INV-'.Str::upper(Str::random(6)),
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'status' => 'pending',
        ];
    }
}
