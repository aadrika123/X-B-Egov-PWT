<?php

/**
 * | Created On-11-08-2022 
 * | Created By-Anshu Kumar
 * | For Modules Master defining constants
 */

return [
    "PROPERTY_WORKFLOW_ID" => "0",
    // SAF Workflow Masters
    "SAF_WORKFLOW_ID"           => "1",
    "SAF_REASSESSMENT_ID"       => "2",
    "SAF_MUTATION_ID"           => "3",
    "SAF_OLD_MUTATION_ID"       => "39",
    "GBSAF_NEW_ASSESSMENT"      => "4",
    "GBSAF_REASSESSMENT"        => "5",
    "SAF_BIFURCATION_ID"        => "6",
    "SAF_AMALGAMATION_ID"       => "7",

    "ULB_WORKFLOW_ID_OLD_MUTATION"    => "202",

    "PROPERTY_DEACTIVATION_MASTER_ID"   => "8",
    "PROPERTY_CONCESSION_ID"            => "9",
    "PROPERTY_OBJECTION_CLERICAL"       => "10",
    "PROPERTY_OBJECTION_ASSESSMENT"     => "11",
    "PROPERTY_OBJECTION_FORGERY"        => "12",
    "RAIN_WATER_HARVESTING_ID"          => "13",
    "PROPERTY_WAIVER_ID"                => "35",
    "PROPERTY_UPDATE_ID"                => "37",

    "WATER_MASTER_ID"                   => "15",
    "WATER_DISCONNECTION"               => "33",
    "TRADE_MASTER_ID"                   => "16",
    "TRADE_TEMP_MASTER_ID"              => "45",
    "TRADE_NOTICE_ID"                   => "17",

    "GENERAL_NOTICE_MASTER_ID"            => "27",
    "DENIAL_NOTICE_MASTER_ID"             => "28",
    "PAYMENT_NOTICE_MASTER_ID"            => "29",
    "ILLEGAL_OCCUPATION_NOTICE_MASTER_ID" => "30",

    "DEALING_ASSISTENT_WF_ID"           => "6",
    "WATER_JE_ROLE_ID"                  => "40",
    "WATER_TEMP_DEACTIVATION"               => "42",
    "WATER_RECONNECTION"                 => "43",

    // User Types
    "USER_TYPES" => [
        "1" => "Citizen",
    ],

    "baseUrl" => "192.168.0.15:8000",

    "ROLES" => [
        "ULB_Tax_Collector" => 7,
        "Tax_Collector" => 5,
        "Team_Leader" => 4
    ],

    "WATER_CONSUMER_WF" => [
        "FERRULE_CLEANING_CHECKING" => "37",
        "PIPE_SHIFTING_ALTERATION"  => "38",
    ],
];
