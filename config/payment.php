<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'mock' => [
        'server_key' => env('PAYMENT_MOCK_SERVER_KEY', 'mock-server-key'),
    ],

    'xendit' => [
        'api_key' => env('XENDIT_API_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],
];
