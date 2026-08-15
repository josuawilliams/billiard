<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBookingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Booking::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', 'success'))
            ->update(['status' => 'expired']);
    }
}
