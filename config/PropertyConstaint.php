<?php

/**
 * | Created On-11-08-2022 
 * | Created By-Sandeep Bara Kumar
 * | For OBJECTION Master defining constants
 */

return [
    "PARAM_RENTAL_RATE" => 144,
    "EFFECTIVE_DATE_RULE2" => "2016-04-01",
    "EFFECTIVE_DATE_RULE3" => "2022-04-01",
    "VACANT_PROPERTY_TYPE" => "4",
    "SAF_TOWARDS" => "Property Tax",
    "ACCOUNT_DESCRIPTION" => "Property Tax",
    "DEPARTMENT_SECTION" => "Revenue Section",
    "SAF_REF_TABLE" => "prop_active_safs.id",
    "SAF_CONCESSION_REF_TABLE" => "prop_active_concessions.id",
    "SAF_OBJECTION_REF_TABLE" => "prop_active_objections.id",
    "SAF_HARVESTING_REF_TABLE" => "prop_active_harvestings.id",
    "RWH_AREA_OF_PLOT" => 3228,

    "JSK_ROLE" => 8,

    "OBJECTION" => [
        "2"     => "RainHarvesting",
        "3"     => "RoadWidth",
        "4"     => "PropertyType",
        "5"     => "AreaOfPlot",
        "6"     => "MobileTower",
        "7"     => "HoardingBoard",
        "9"     => "FloorDetail"
    ],

    "INDEPENDENT_PROP_TYPE_ID" => "2",       // Individual Property 

    "PROPERTY-TYPE" => [
        "1"     => "SUPER STRUCTURE",
        "2"     => "INDEPENDENT BUILDING",
        "3"     => "FLATS / UNIT IN MULTI STORIED BUILDING",
        "4"     => "VACANT LAND",
        "5"     => "OCCUPIED PROPERTY"
    ],
    "TRANSFER_MODES" => [
        "1" => "Sale",
        "2" => "Gift",
        "3" => "Will",
        "4" => "Lease",
        "5" => "Partition",
        "6" => "Succession",
        "7" => "Heridity/Will/Gift",
        "8" => "Sale/Purchase/Lease",
        "9" => "Imla"
    ],
    "OWNERSHIP-TYPE" => [
        "1"     => "INDIVIDUAL",
        "2"     => "CO-OPERATIVE SOCIETY",
        "3"     => "RELIGIOUS TRUST",
        "4"     => "TRUST",
        "5"     => "STATE GOVT",
        "6"     => "CENTRAL GOVT",
        "7"     => "STATE PSU",
        "8"     => "CENTRAL PSU",
        "9"     => "BOARD",
        "10"     => "TCOMPANY PUBLIC LTD",
        "11"     => "INSTITUTE",
        "12"     => "OCCUPIER",
        "15"     => "COMPANY PRIVATE LTD",
        "13"     => "OTHER"

    ],
    "FLOOR-TYPE" => [
        "1"   =>  "PARKING",
        "2"   =>  "BASEMENT",
        "3"   =>  "Ground Floor",
        "4"   =>  "1st Floor",
        "5"   =>  "2nd Floor",
        "6"   =>  "3rd Floor",
        "7"   =>  "4th Floor",
        "8"   =>  "5th Floor",
        "9"   =>  "6th Floor",
        "10"   =>  "7th Floor",
        "11"   =>  "8th Floor",
        "12"   =>  "9th Floor",
        "13"  => "10th Floor",
        "14"  => "11th Floor",
        "15"  => "12th Floor",
        "16"  => "13th Floor",
        "17"  => "14th Floor",
        "18"  => "15th Floor",
        "19"  => "16th Floor",
        "20"  => "17th Floor",
        "21"  => "19th Floor",
        "22"  => "20th Floor",
        "23"  => "21th Floor",
        "24"  => "22th Floor",
        "25"  => "23th Floor",
        "26"  => "24th Floor",
        "27"  => "25th Floor",

    ],
    "OCCUPANCY-TYPE" => [
        "1" =>  "SELF OCCUPIED",
        "2" =>  "RENTED",
        "RENTED" => '2',
        "2" =>  "TENANTED",
        "TENANTED" => '2'

    ],

    "RELIGIOUS_PLACE_USAGE_TYPE_ID" => "11",                // Religious Place usage type
    "TRUST_USAGE_TYPE_ID" => "43",                          // Trust usage type

    "USAGE-TYPE" => [
        "1" => [
            "CODE" => "A",
            "TYPE" => "RESIDENTIAL"
        ],
        "7" => [
            "CODE" => "G",
            "TYPE" => "COMMERCIAL ESTABLISHMENTS AND UNDERTAKING OF STATE AND CENTRAL GOVERNMENT"
        ],
        "9" => [
            "CODE" => "I",
            "TYPE" => "STATE AND CENTRAL GOVERNMENT OFFICES OTHER THAN COMMERCIAL ESTABLISHMENT AND UNDERTAKINGS"
        ],
        "11" => [
            "CODE" => "K",
            "TYPE" => "RELIGIOUS AND SPIRITUAL PLACES"
        ],
        "13" => [
            "CODE" => "B",
            "TYPE" => "HOTEL"
        ],
        "14" => [
            "CODE" => "B",
            "TYPE" => "BARS"
        ],
        "15" => [
            "CODE" => "B",
            "TYPE" => "CLUBS"
        ],

        "16" => [
            "CODE" => "B",
            "TYPE" => "HEALTH CLUB"
        ],
        "17" => [
            "CODE" => "B",
            "TYPE" => "MARRIAGE HALLS"
        ],
        "18" => [
            "CODE" => "C",
            "TYPE" => "SHOP WITH LESS THAN 250 SQ. FEET"
        ],
        "19" => [
            "CODE" => "D",
            "TYPE" => "SHOW ROOM"
        ],
        "20" => [
            "CODE" => "D",
            "TYPE" => "SHOPPING MALLS"
        ],
        "21" => [
            "TYPE" => "CINEMA HOUSES",
            "CODE" => "D"
        ],
        "22" => [
            "CODE" => "D",
            "TYPE" => "MULTIPLEXES",

        ],
        "23" => [
            "CODE" => "D",
            "TYPE" => "DISPENSARIES",

        ],
        "24" => [
            "CODE" => "D",
            "TYPE" => "LABORATORIES",

        ],
        "25" => [
            "CODE" => "D",
            "TYPE" => "RESTURANTS",

        ],
        "26" => [
            "CODE" => "D",
            "TYPE" => "GUEST HOUSES",

        ],
        "27" => [
            "CODE" => "E",
            "TYPE" => "COMMERCIAL OFFICES",

        ],
        "28" => [
            "CODE" => "E",
            "TYPE" => "FINANCIAL INSTITUTIONS",

        ],
        "29" => [
            "CODE" => "E",
            "TYPE" => "BANKS",

        ],
        "30" => [
            "CODE" => "E",
            "TYPE" => "INSURANCE OFFICES",

        ],
        "31" => [
            "CODE" => "E",
            "TYPE" => "PRIVATE HOSPITALS",

        ],
        "32" => [
            "CODE" => "E",
            "TYPE" => "NURSING HOMES",

        ],
        "33" => [
            "CODE" => "F",
            "TYPE" => "INDUSTRIES",

        ],
        "34" => [
            "CODE" => "F",
            "TYPE" => "WORKSHOPS",

        ],
        "35" => [
            "CODE" => "F",
            "TYPE" => "STORAGE",

        ],
        "36" => [
            "CODE" => "F",
            "TYPE" => "GODOWNS",

        ],
        "37" => [
            "CODE" => "F",
            "TYPE" => "WARE HOUSES",

        ],
        "38" => [
            "CODE" => "H",
            "TYPE" => "COACHING CLASSES",

        ],
        "39" => [
            "CODE" => "H",
            "TYPE" => "GUIDANCE & TRAINING CENTRES & THEIR HOSTELS",

        ],
        "40" => [
            "CODE" => "J",
            "TYPE" => "PRIVATE SCHOOLS",

        ],
        "41" => [
            "CODE" => "J",
            "TYPE" => "PRIVATE COLLEGES",

        ],
        "42" => [
            "CODE" => "J",
            "TYPE" => "PRIVATE RESEARCH INSTITUTION AND OTHER PRIVATE EDUCATIONAL INSTITUTIONS AND THEIT HOSTELS",

        ],
        "43" => [
            "CODE" => "L",
            "TYPE" => "EDUCATIONAL & SOCIAL INSTITUTIONS RUN BY TRUST",

        ],
        "44" => [
            "CODE" => "L",
            "TYPE" => "NGOS ON NO-PROFIT",

        ],
        "45" => [
            "CODE" => "L",
            "TYPE" => "NO-LOSS BASIS",

        ],
        "46" => [
            "CODE" => "M",
            "TYPE" => "OTHERS",

        ],
    ],

    /**
     * | The type of Usage Types which is assured to be tax perc 0.20 if their buildup area is more than 25000 sqft
     */
    "POINT20-TAXED-COMM-USAGE-TYPES" => [2, 4, 13, 17, 20, 22],

    "CONSTRUCTION-TYPE" => [
        "1" => "Pucca with RCC Roof (RCC)",
        "2" => "Pucca with Asbestos/Corrugated Sheet (ACC)",
        "3" => "Kuttcha with Clay Roof (Other)",
    ],

    // Property Assessment Type
    "ASSESSMENT-TYPE" =>
    [
        "1" => "New Assessment",
        "2" => "Reassessment",
        "3" => "Mutation",
        "4" => "Bifurcation",
        "5" => "Amalgamation"
    ],

    // Property Assessment Types for kind of properties in which old prop id required
    "REASSESSMENT_TYPES" =>
    [
        "Reassessment",
        "Mutation",
        "Bifurcation"
    ],

    "ULB-TYPE-ID" => [
        "Municipal Carporation" => 1,
        "Nagar Parishad" => 2,
        "Nagar Panchayat" => 3
    ],
    "MATRIX-FACTOR" => [
        "2" => [
            "1" => 1,
            "2" => 1,
            "3" => 0.5,
        ],
        "3" => [
            "1" => 0.8,
            "2" => 0.8,
            "3" => 0.4
        ],
    ],
    "CIRCALE-RATE-ROAD" => [
        "1" => "_main",
        "2" => "_main",
        "3" => "_other",
        "4" => "_other",
        // RuleSet 3 Circle Rate Road
        "2022-04-01" => [
            "1" => "_main",
            "2" => "_other",
            "3" => "_other",
            "4" => "_other",
        ]
    ],
    "CIRCALE-RATE-PROP" => [
        "0" => "_apt",
        "1" => "_pakka",
        "2" => "_pakka",
        "3" => "_kuccha",

        // Ruleset3 Circle Rate Construction Type
        "2022-04-01" => [
            "0" => "_apt",
            "1" => "_pakka",
            "2" => "_kuccha",
            "3" => "_kuccha",
        ]
    ],
    "CIRCALE-RATE-USAGE" => [
        "1" => "res",
        "2" => "com",
    ],

    // Label Role ID
    "SAF-LABEL" => [
        "BO" => "11",
        "DA" => "6",
        "TC" => "5",
        "UTC" => "7",
        "SI" => "9",
        "EO" => "10"
    ],

    //GB SAF levels
    "GBSAF-LABEL" => [
        "BO" => "11",
        "DA" => "6",
        "TC" => "5",
        "UTC" => "7",
        "SI" => "9",
        "EO" => "10"
    ],

    //Harvesting Label Role ID
    "HARVESTING-LABEL" => [
        "BO" => "11",
        "TC" => "5",
        "UTC" => "7",
        "SI" => "9",
        "EO" => "10"
    ],

    //Concession Label Role ID
    "CONCESSION-LABEL" => [
        "BO" => "11",
        "DA" => "6",
        "SI" => "9",
        "EO" => "10"
    ],

    //Objection Label Role ID
    "OBJECTION-LABEL" => [
        "BO" => "11",
        "SI" => "9",
        "EO" => "10"
    ],

    "VACANT_LAND"   => "4",
    // Saf Pending Status
    "SAF_PENDING_STATUS" => [
        "NOT_APPROVED" => 0,
        "APPROVED" => 1,
        "BACK_TO_CITIZEN" => 2,
        "LABEL_PENDING" => 3
    ],

    // Relative GeoTagging Path of Geo Tagging
    "GEOTAGGING_RELATIVE_PATH" => "Uploads/Property/GeoTagging",
    "SAF_RELATIVE_PATH"        => "Uploads/Property/Saf",
    "PROCCESS_RELATIVE_PATH"   => "Uploads/Property/PROCESS_FEE",
    "OBJECTION_RELATIVE_PATH"  => "Uploads/Property/Objection",
    "CONCESSION_RELATIVE_PATH" => "Uploads/Property/Concession",
    "HARVESTING_RELATIVE_PATH" => "Uploads/Property/Harvesting",
    "WAIVER_RELATIVE_PATH"     => "Uploads/Property/Waiver",
    "CUSTOM_RELATIVE_PATH"     => "Uploads/Custom",
    "PROP_UPDATE_RELATIVE_PATH" => "Uploads/Property/UpdateRequest",

    // Rebates
    "REBATES" => [
        "CITIZEN" => [
            "ID" => 1,
            "PERC" => 5,
            "KEY" => ""
        ],
        "JSK" => [
            "ID" => 2,
            "PERC" => 2.5
        ],
        "SPECIALLY_ABLED" => [
            "ID" => 3,
            "PERC" => 5
        ],
        "SERIOR_CITIZEN" => [
            "ID" => 4,
            "PERC" => 5
        ]
    ],

    // Penalties
    "PENALTIES" => [
        "RWH_PENALTY_ID" => 1,
        "LATE_ASSESSMENT_ID" => 2       // One Perc Penalty
    ],

    /**
     * | Id Generation Parmam Ids
     */

    "PARAM_ID"      => 1,
    "PT_PARAM_ID"   => 3,
    "GB_PARAM"      => 4,
    "SAM_PARAM_ID"  => 5,
    "PROP_FULL_UPDATE_ID"  => 44,
    "PROP_BASIC_UPDATE_ID"  => 45,
    "FAM_PARAM_ID"  => 6,
    "CON_PARAM_ID"  => 7,
    "OBJ_PARAM_ID"  => 8,
    "HAR_PARAM_ID"  => 9,
    "DEACTIV_PARAM_ID"  => 24,
    "CASH_VERIFICATION_PARAM_ID"  => 33,
    "WAIVER_PARAM_ID"  => 40,


    /**
     * | Rebate and Penalty Masters
     */
    "REBATE_PENAL_MASTERS" => [
        [
            "id" => 1,                      // Used
            "key" => "onePercPenalty",
            "value" => "1% Monthly Penalty"
        ],
        [
            "id" => 2,                      // Used
            "key" => "firstQtrRebate",
            "value" => "First Qtr Rebate",
            "perc" => 5,
        ],
        [
            "id" => 3,                      // Used
            "key" => "onlineRebate",
            "value" => "Rebate From Jsk/Online Payment",
            "perc" => 5
        ],
        [
            "id" => 4,
            "key" => "onlineRebate",
            "value" => "Rebate From Jsk/Online Payment",
            "perc" => 2.5
        ],
        [
            "id" => 5,                      // Used
            "key" => "lateAssessmentFine",
            "value" => "Late Assessment Fine(Rule 14.1)"
        ],
        [
            "id" => 6,
            "key" => "specialRebate",
            "value" => "Special Rebate",
            "perc" => 5
        ],
        [
            "id" => 7,
            "key" => "onlineRebate5%",
            "value" => "Online Rebate",
            "perc" => 5
        ],
        [
            "id" => 8,
            "key" => "jskRebate2.5%",
            "value" => "JSK (2.5%) Rebate",
            "perc" => 5
        ],
    ],

    // Adjustment Types 
    "ADJUSTMENT_TYPES" => [
        "ULB_ADJUSTMENT" => "Demand Adjustment",
    ],

    // Doc Codes
    "TRUST_DOC_CODE" => "TRUST_DOCUMENT",

    // Property Payment Receipts Rebate Penalty Key Strings
    "PENALTY_REBATE_KEY_STRINGS" => [
        "lateAssessmentPenalty" => "Late Assessment Fine(Rule 14.1)",
        "onePercPenalty" => "1% Interest On Monthly Penalty",
        "rebate" => "Rebate",
        "onlineOrJskRebate" => "Rebate From Jsk/Online Payment",
        "specialRebate" => "Special Rebate",
        "firstQtrRebate" => "First Qtr Rebate",
    ],

    // Road Types
    "ROAD_TYPES" => [
        "1" => "Principal Main Road",
        "2" => "Main Road",
        "3" => "Other",
        "4" => "No Road"
    ],

    // Read Aging By Floors
    "AGING_PERC" => [
        // 11 to 30 years
        [
            "id" => 1,
            "const_id" => 1,
            "aging_perc" => 5,
            "range_from" => 11,
            "range_upto" => 30,
        ],
        [
            "id" => 2,
            "const_id" => 2,
            "aging_perc" => 10,
            "range_from" => 11,
            "range_upto" => 30,
        ],
        [
            "id" => 3,
            "const_id" => 3,
            "aging_perc" => 15,
            "range_from" => 11,
            "range_upto" => 30,
        ],
        [
            "id" => 4,
            "const_id" => 4,
            "aging_perc" => 20,
            "range_from" => 11,
            "range_upto" => 30,
        ],
        [
            "id" => 5,
            "const_id" => 5,
            "aging_perc" => 20,
            "range_from" => 11,
            "range_upto" => 30,
        ],

        // 31 to 50 years
        [
            "id" => 6,
            "const_id" => 1,
            "aging_perc" => 10,
            "range_from" => 31,
            "range_upto" => 50,
        ],
        [
            "id" => 7,
            "const_id" => 2,
            "aging_perc" => 15,
            "range_from" => 31,
            "range_upto" => 50,
        ],
        [
            "id" => 8,
            "const_id" => 3,
            "aging_perc" => 20,
            "range_from" => 31,
            "range_upto" => 50,
        ],
        [
            "id" => 9,
            "const_id" => 4,
            "aging_perc" => 25,
            "range_from" => 31,
            "range_upto" => 50,
        ],
        [
            "id" => 10,
            "const_id" => 5,
            "aging_perc" => 25,
            "range_from" => 31,
            "range_upto" => 50,
        ],

        // After 50 Years
        [
            "id" => 11,
            "const_id" => 1,
            "aging_perc" => 15,
            "range_from" => 51,
            "range_upto" => null,
        ],
        [
            "id" => 12,
            "const_id" => 2,
            "aging_perc" => 20,
            "range_from" => 51,
            "range_upto" => null,
        ],
        [
            "id" => 13,
            "const_id" => 3,
            "aging_perc" => 25,
            "range_from" => 51,
            "range_upto" => null,
        ],
        [
            "id" => 14,
            "const_id" => 4,
            "aging_perc" => 30,
            "range_from" => 51,
            "range_upto" => null,
        ],
        [
            "id" => 15,
            "const_id" => 5,
            "aging_perc" => 30,
            "range_from" => 51,
            "range_upto" => null,
        ],
    ]
];
