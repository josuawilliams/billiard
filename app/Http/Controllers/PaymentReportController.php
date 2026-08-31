<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('from', now()->startOfMonth()->toDateString());
        $endDate = $request->input('to', now()->endOfMonth()->toDateString());
        $search = $request->input('search');

        $query = Payment::with(['booking.user', 'booking.table']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('booking.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('booking.table', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $payments = (clone $query)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->paginate(20);

        $summary = (clone $query)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select([
                DB::raw('SUM(CASE WHEN status = \'success\' THEN amount ELSE 0 END) as total_omzet'),
                DB::raw('SUM(CASE WHEN status = \'pending\' THEN amount ELSE 0 END) as total_pending'),
                DB::raw('SUM(CASE WHEN status = \'failed\' THEN amount ELSE 0 END) as total_failed'),
                DB::raw('COUNT(CASE WHEN status = \'success\' THEN 1 END) as count_success'),
                DB::raw('COUNT(CASE WHEN status = \'pending\' THEN 1 END) as count_pending'),
                DB::raw('COUNT(CASE WHEN status = \'failed\' THEN 1 END) as count_failed'),
                DB::raw('COUNT(*) as count_total'),
            ])
            ->first();

        $dailyOmzet = (clone $query)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'success')
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->select([
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
            ])
            ->orderBy('date', 'desc')
            ->get();

        return ApiResponse::success([
            'summary' => [
                'total_omzet' => $summary->total_omzet ?? 0,
                'total_pending' => $summary->total_pending ?? 0,
                'total_failed' => $summary->total_failed ?? 0,
                'count_success' => (int) ($summary->count_success ?? 0),
                'count_pending' => (int) ($summary->count_pending ?? 0),
                'count_failed' => (int) ($summary->count_failed ?? 0),
                'count_total' => (int) ($summary->count_total ?? 0),
            ],
            'daily_omzet' => $dailyOmzet,
            'payments' => $payments,
            'filters' => [
                'from' => $startDate,
                'to' => $endDate,
                'search' => $search,
            ],
        ], 'Payment report retrieved');
    }
}
