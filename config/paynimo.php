<?php
return [
    'merchant_code' => env('PAYNIMO_MERCHANT_CODE'),
    'api_key' => env('PAYNIMO_API_KEY', ''),
    'salt' => env('PAYNIMO_SALT'),
    'env' => env('PAYNIMO_ENV'), // 'test' or 'prod'
    'PAYMENT_URL' => env('PAYMENT_URL'), // 'test' or 'prod'
];
