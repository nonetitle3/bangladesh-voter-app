<?php
return [
    'python_worker' => [
        'url' => env('PYTHON_WORKER_URL', ''),
        'token' => env('PYTHON_WORKER_TOKEN', ''),
        'timeout' => (int) env('PYTHON_WORKER_TIMEOUT', 1500),
    ],
];
