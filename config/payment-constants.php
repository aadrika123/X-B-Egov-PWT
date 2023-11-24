<?php

/**
 * | Created On-14-02-2022 
 * | Created By-Anshu Kumar
 * | Created for- Payment Constants Masters
 */
return [
    "ULB_LOGO_URL" =>  env("ulb_logo_url", "http://localhost/"),
    "PROPERTY_FRONT_URL" => (env("FRONT_URL", "https://modernulb.com") . "/citizen/property/payment-status"),
    "WATER_FAIL_URL"    => (env("FRONT_URL","https://modernulb.com") . "/water/water-demand-payment/"),
    'PAYMENT_MODE' => [
        '1' => 'ONLINE',
        '2' => 'NETBANKING',
        '3' => 'CASH',
        '4' => 'CHEQUE',
        '5' => 'DD',
        '6' => 'NEFT'
    ],

    'PAYMENT_MODE_OFFLINE' => [
        'CASH',
        'CHEQUE',
        'DD',
        'NEFT'
    ],

    "VERIFICATION_PAYMENT_MODES" => [           // The Verification payment modes which needs the verification
        "CHEQUE",
        "DD",
        "NEFT"
    ],


    'PAYMENT_OFFLINE_MODE_WATER' => [
        'Cash',
        'Cheque',
        'DD',
        'Neft'
    ],

    "VERIFICATION_PAYMENT_MODE" => [
        'Cheque',
        'DD',
        'Neft'
    ],

    'ONLINE' => "Online",
    "PAYMENT_OFFLINE_MODE" => [
        "1" => "Cash",
        "2" => "Cheque",
        "3" => "DD",
        "4" => "Neft",
        "5" => "Online"
    ],
    "REF_PAY_MODE" => [
        "CASH"      => "Cash",
        "CHEQUE"    => "Cheque",
        "DD"        => "DD",
        "NEFT"      => "Neft",
        "ONLINE"    => "Online"
    ],
    "TRAN_PARAM_ID" => 37,

    "PAYMENT_STATUS" => [
        "PENDING"   => 0,
        "APPROVED"  => 1,
        "REJECT"    => 2
    ],

    "PINELAB_RESPONSE_CODE" => [
        0 => "Success",
        1 => "App Not Activated",
        2 => "Already Activated",
        3 => "Invalid Method Id",
        4 => "Invalid User/Pin",
        5 => "User Blocked For Max Attempt",
        6 => "Permission Denied For This User ",
        7 => "Invalid Data Format",
    ],
];
