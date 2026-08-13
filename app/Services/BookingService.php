<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function create(array $data, User $user): Booking
    {
        $table = Table::findOrFail($data['table_id']);

        $start = Carbon::parse($data['start_time']);
        $end = $start->copy()->addHours((int) $data['duration']);

        $totalPrice = $table->price_per_hour * (int) $data['duration'];

        return DB::transaction(function () use ($data, $user, $table, $start, $end, $totalPrice) {
            $overlap = Booking::where('table_id', $table->id)
                ->where('booking_date', $data['booking_date'])
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '<', $end->format('H:i'))
                ->where('end_time', '>', $data['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new BookingConflictException('Table is already booked for that time slot.');
            }

            return Booking::create([
                'user_id' => $user->id,
                'table_id' => $table->id,
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $end->format('H:i'),
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);
        });
    }
}