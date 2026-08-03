<?php

return [
    'echo' => [
        'VITE_BROADCAST_CONNECTION' => env('VITE_BROADCAST_CONNECTION') ?: env('BROADCAST_CONNECTION', 'null'),
        'VITE_REVERB_APP_KEY' => env('VITE_REVERB_APP_KEY') ?: env('REVERB_APP_KEY'),
        'VITE_REVERB_HOST' => env('VITE_REVERB_HOST') ?: env('REVERB_HOST'),
        'VITE_REVERB_PORT' => env('VITE_REVERB_PORT') ?: env('REVERB_PORT'),
        'VITE_REVERB_SCHEME' => env('VITE_REVERB_SCHEME') ?: env('REVERB_SCHEME', 'https'),
        'VITE_PUSHER_APP_KEY' => env('VITE_PUSHER_APP_KEY') ?: env('PUSHER_APP_KEY'),
        'VITE_PUSHER_APP_CLUSTER' => env('VITE_PUSHER_APP_CLUSTER') ?: env('PUSHER_APP_CLUSTER'),
        'VITE_PUSHER_HOST' => env('VITE_PUSHER_HOST'),
        'VITE_PUSHER_PORT' => env('VITE_PUSHER_PORT') ?: env('PUSHER_PORT'),
        'VITE_PUSHER_SCHEME' => env('VITE_PUSHER_SCHEME') ?: env('PUSHER_SCHEME', 'https'),
    ],
];
