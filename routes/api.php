<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return \App\Support\ApiResponse::success(['pong' => true], 'Pong');
});
