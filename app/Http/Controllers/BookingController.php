<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingConflictException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with('table', 'payment')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($bookings, 'Bookings retrieved');
    }

    public function store(StoreBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->create($request->validated(), $request->user());

            return ApiResponse::success($booking->load('table'), 'Booking created', 201);
        } catch (BookingConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }
    }

    public function show(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success($booking->load('table', 'payment'), 'Booking retrieved');
    }

    public function adminBookings(Request $request)
    {
        $bookings = Booking::with('user', 'table', 'payment')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->booking_date, fn ($q, $date) => $q->where('booking_date', $date))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success($bookings, 'Bookings retrieved');
    }
}