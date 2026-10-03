<?php

use App\Models\User;
use App\Models\Customer;

return [

    // Global switch for emailed login codes (staff and customers). When on,
    // the per-user two_factor_enabled flag still applies.
    'two_factor' => (bool) env('TWO_FACTOR_ENABLED', true),

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        // Staff guard – uses the users table
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Customer portal guard – uses the customers table
        'customer' => [
            'driver'   => 'session',
            'provider' => 'customers',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => User::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model'  => Customer::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],

        'customers' => [
            'provider' => 'customers',
            'table'    => 'customer_password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
