<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'mock' => [
        'server_key' => env('PAYMENT_MOCK_SERVER_KEY', 'mock-server-key'),
    ],
];
