<?php

use App\Jobs\ExpireBookingJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireBookingJob)->everyMinute();
