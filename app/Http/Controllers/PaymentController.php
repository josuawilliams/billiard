<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function create(CreatePaymentRequest $request)
    {
        $booking = $request->user()->bookings()->findOrFail($request->booking_id);

        if ($booking->status !== 'pending') {
            return ApiResponse::error('Booking is not payable', 422);
        }

        if ($booking->payment()->where('status', 'success')->exists()) {
            return ApiResponse::error('Booking already paid', 422);
        }

        $result = $this->paymentService->createPayment($booking);

        return ApiResponse::success($result, 'Payment created. Redirect customer to Midtrans Snap.', 201);
    }

    public function status(Request $request, Payment $payment)
    {
        if ($payment->booking->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success($payment->load('booking'), 'Payment status retrieved');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (! $this->paymentService->verifyWebhook($payload)) {
            return ApiResponse::error('Invalid signature', 401);
        }

        $payment = Payment::where('transaction_id', $payload['order_id'])->first();

        if (! $payment) {
            return ApiResponse::error('Payment not found', 404);
        }

        if ($payment->status === 'success') {
            return ApiResponse::success($payment, 'Payment already processed');
        }

        $isSuccess = in_array($payload['transaction_status'] ?? null, ['capture', 'settlement']);

        DB::transaction(function () use ($payment, $isSuccess) {
            if ($isSuccess) {
                $payment->update(['status' => 'success', 'paid_at' => now()]);
                $payment->booking()->update(['status' => 'paid']);
            } else {
                $payment->update(['status' => 'failed']);
            }
        });

        return ApiResponse::success($payment->fresh(), 'Webhook processed');
    }

    public function adminPayments(Request $request)
    {
        $payments = Payment::with('booking.user', 'booking.table')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success($payments, 'Payments retrieved');
    }
}
