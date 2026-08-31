<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function schedule(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $openTime = $request->input('open_time', '10:00');
        $closeTime = $request->input('close_time', '24:00');

        $tables = Table::with(['bookings' => function ($query) use ($date) {
            $query->where('booking_date', $date)
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time', 'asc');
        }])->get();

        $result = $tables->map(function ($table) use ($openTime, $closeTime) {
            $slots = [];
            $currentPointer = $openTime;

            foreach ($table->bookings as $booking) {
                $bookingStart = Carbon::parse($booking->start_time)->format('H:i');
                $bookingEnd = Carbon::parse($booking->end_time)->format('H:i');

                // Jika ada jeda kosong sebelum booking ini
                if ($currentPointer < $bookingStart) {
                    $slots[] = [
                        'status' => 'available',
                        'start_time' => $currentPointer,
                        'end_time' => $bookingStart,
                    ];
                }

                // Slot yang sudah ter-booking
                $slots[] = [
                    'status' => 'booked',
                    'start_time' => $bookingStart,
                    'end_time' => $bookingEnd,
                ];

                $currentPointer = max($currentPointer, $bookingEnd);
            }

            // Sisa waktu sampai jam tutup
            if ($currentPointer < $closeTime) {
                $slots[] = [
                    'status' => 'available',
                    'start_time' => $currentPointer,
                    'end_time' => $closeTime,
                ];
            }

            return [
                'id' => $table->id,
                'name' => $table->name,
                'price_per_hour' => $table->price_per_hour,
                'schedules' => $slots,
            ];
        });

        return ApiResponse::success($result, 'Table schedules retrieved');
    }

    public function index()
    {
        $tables = Table::withCount('bookings')->get();

        return ApiResponse::success($tables, 'Tables retrieved');
    }

    public function store(StoreTableRequest $request)
    {
        $table = Table::create($request->validated());

        return ApiResponse::success($table, 'Table created', 201);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $table->update($request->validated());

        return ApiResponse::success($table, 'Table updated');
    }

    public function destroy(Table $table)
    {
        $table->delete();

        return ApiResponse::success(null, 'Table deleted');
    }
}
