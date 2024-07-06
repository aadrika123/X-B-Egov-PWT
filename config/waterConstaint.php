<?php

return [
    // Label Role ID (waterConstaint)

    "CONNECTION_THROUGH" => [
        "HOLDING" => 1,
        "SAF" => 2
    ],
    "METER_CONN_TYPE" => [
        "1" => "Meter",
        "2" => "Gallon",
        "3" => "Fixed",
        "4" => "Meter/Fixed",
        "5" => "Average"
    ],
    "ROLE-LABEL" => [
        "BO" => 11,
        "DA" => 6,
        "JE" => 12,     // 40
        "SH" => 13,
        "AE" => 14,
        "EO" => 10
    ],

    "PARAM_IDS" => [
        "WAPP"  => 15,
        "WCON"  => 16,
        "TRN"   => 37,
        "WCD"   => 39,
        "WFC"   => 42,
        "WPS"   => 43
    ],
    "ACCOUNT_DESCRIPTION" => "Water",
    "DEPARTMENT_SECTION" => "Revenue Section",
    "TOWARDS" => "Water Connection and Others",
    "TOWARDS_DEMAND" => "Water User Charges",
    "WATER_DEPAPRTMENT_ID" => "2",
    "WATER_RELATIVE_PATH" => "Uploads/Water",
    "WATER_UPDATE_RELATIVE_PATH" => "Uploads/Water/Consumer/UpdateLog",

    "PROPERTY_TYPE" => [
        "Residential"       => 1,
        "Industrial"        => 6,
        "Government"        => 3,
        "Commercial"        => 2,
        "Institutional"     => 4,
        "Apartment"         => 7,
        "MultiStoredUnit"   => 8
    ],

    "DESMIL_TO_SQFT" => 435.6,

    "CONNECTION_TYPE" => [
        "NEW_CONNECTION"    => 1,
        "REGULAIZATION"     => 2,
        "SITE_INSPECTON"    => 5,                                       // static
    ],

    "REF_CONNECTION_TYPE" => [
        "New Connection"    => 1,
        "Regulaization"     => 2
    ],

    "JSK" => "JSK",

    "CHARGE_CATAGORY" => [
        "SITE_INSPECTON"    => "Site Inspection",
        "NEW_CONNECTION"    => "New Connection",
        "REGULAIZATION"     => "Regulaization"
    ],

    "FILTER_BY" => [
        "APPLICATION"       => "byApplication",
        "DATE"              => "byDate",
        "APPLICATION_ID"    => "byApplicationId"
    ],

    "New_Connection" => "connection",
    "WATER_MASTER_DATA" => [
        "PIPELINE_SIZE_TYPE" => [
            "CI",
            "DI"
        ],
        "PIPE_DIAMETER" => [
            "15",
            "20",
            "25"
        ],
        "PIPE_QUALITY" => [
            "GI",
            "HDPE",
            "PVC 80"
        ],
        "ROAD_TYPE" => [
            "RMC",
            "PWD"
        ],
        "FERULE_SIZE" => [
          "15",
          "20",
          "25",
          "50",
          "65",
          "75",
          "80",
          "100",
          "150",
          "200"

        ],
        "DEACTIVATION_CRITERIA" => [
            "Double Connection",
            "Waiver Committee",
            "No Connection"
        ],
        "METER_CONNECTION_TYPE" => [
            "Meter"         => 1,
            "Gallon"        => 2,
            "Fixed"         => 3,
            "Meter/Fixed"   => 4
        ],
    ],

    "WATER_METER_CODE" => "Meter",
    "WATER_FIXED_CODE" => "MeterFixed",
    "WATER_ADVANCE_CODE" => "WaterAdvance",
    "PENALTY_HEAD" => [
        "1" => "1 Installment",
        "2" => "2 Installment",
        "3" => "3 Installment",
        "4" => "Installment Rebate",
    ],

    "USER_TYPE" => [
        'Tax_Collector' => 'TC',
        'Jsk'           => 'JSK',
        'Citizen'       => 'Citizen'
    ],
    "WATER_CONSUMER_DEACTIVATION" => "consumerDeactivation",
    "DEACTIVATION_REASON" => [
        "1" => "Duble Connection",
        "2" => "Waiver Committee",
        "3" => "No Connection"
    ],
    "ADVANCE_FOR" => [
        "1" => "consumer",
        "2" => "connection"
    ],
    "WATER_HEAD_NAME" => [
        "1" => "Installment Rebate",
        "2" => "1.5% Penalty",
        "3" => "10% Rebate"
    ],
    "PAYMENT_FOR" => [
        "1" => "Demand Collection",
        "4" => "Ferrule Cleaning Checking",
        "5" => "Pipe Shifting Alteration",
        "2" => "Water Disconnection",

    ],
    "APP_APPLY_FROM" => [
        "1" => "Online",
    ],
    "REF_USER_TYPE" => [
        "1" => "Citizen",
        "2" => "JSK",
        "3" => "TC",
        "4" => "Pseudo",
        "5" => "Employee"
    ],
    "CONSUMER_CHARGE_CATAGORY" => [
        "FIXED_TO_METER"            => 1,
        "WATER_DISCONNECTION"       => 2,
        "NAME_TRANSFER"             => 3,
        "FERRULE_CLEANING_CHECKING" => 4,
        "PIPE_SHIFTING_ALTERATION"  => 5
    ],

    "PARAM_PIPELINE" => [
        "Old Pipeline" => 1,
        "New Pipeline" => 2
    ],

    "AKOLA_CATEGORY" => [
        "Slum"      => 1,
        "General"   => 2
    ],
    "PAYMENT_FOR_CONSUMER" => [
        "1" => "NEW_CONNECTION",
    ],

    'WATER_CONSUMER_MASTSER_DATA' => [
        "PIPE_DIAMETER" => [
            "15",
            "20",
            "25"
        ],
        "PROPERTY_TYPE" => [
            'id' => 1,
            'name' => 'Residential',
            [
                'id' => 2,
                'name' => 'Commercial'
            ]
        ],
        "CATEGORY" => [
            "SLUM",
            "GENERAL"
        ],
    ],
    'water_property_type_mstr' => [
        [
            'id' => 1,
            'property_type' => 'Residential',
        ],
        [
            'id' => 2,
            'property_type' => 'Commercial',
        ],

    ],
    
    "DOC_URL" => env("DOC_URL"),


];
