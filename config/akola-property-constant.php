<?php

/**
 * | Created at-22-08-2023 
 * | Created By-Anshu Kumar
 * | Created for - Akola Related constants
 * | Status-Closed
 */

use Carbon\Carbon;

return [
    "RESIDENTIAL_USAGE_TYPE" => [34,36,45,52, 17,40,53,54,56,57],
    "GOV_USAGE_TYPE"        =>[17,40,53,54,56,57],
    "ARREAR_TILL_FYEAR" => "2022-2023",
    "ULB_TOTAL_TAXES" => 44,
    "MANGAL_TOWER_ID" => [
        49,
        28,
    ],
    "LESS_PERSENTAGE_APPLY_WARD_IDS"=>[
        #ID   , #WARD NO
        "10"  , # A8
        "11"  , # A9
        "2"   , # A10
        "3"   , # A11  

        "16"  , # B12
        "17"  , # B13
        "18"  , # B14

        "28"  , # C10
        "29"  , # C10

        "49"  , # D9
        "39"  , # D10
        "40"  , # D11
        "41"  , # D12
    ],
    "LESS_PERSENTAGE_APPLY_FYEARS"=>[
        "2021-2022"=>0.8,
        "2020-2021"=>0.6,
        "2019-2020"=>0.4,
        "2018-2019"=>0.2,
    ],
    "FIRST_QUIETER_REBATE"=>[
        "0"=>[
            "rebate_type"=>"First Quieter Rebate",
            "effective_from"=>"2024-04-01",
            "from_date"=>Carbon::parse(Carbon::now()->format("Y")."-04-01")->format("Y-m-d"),
            "upto_date"=>Carbon::parse(Carbon::now()->format("Y")."-06-14")->format("Y-m-d"),
            "rebates"=>7,
            "rebates_in_perc"=>true,
            "apply_on_current_tax"=>true,
        ],
        "1"=>[
            "rebate_type"=>"First Quieter Rebate",
            "effective_from"=>"2024-04-01",
            "from_date"=>Carbon::parse(Carbon::now()->format("Y")."-06-15")->format("Y-m-d"),
            "upto_date"=>Carbon::parse(Carbon::now()->format("Y")."-07-14")->format("Y-m-d"),
            "rebates"=>6,
            "rebates_in_perc"=>true,
            "apply_on_current_tax"=>true,
        ],
        "2"=>[
            "rebate_type"=>"First Quieter Rebate",
            "effective_from"=>"2024-04-01",
            "from_date"=>Carbon::parse(Carbon::now()->format("Y")."-07-15")->format("Y-m-d"),
            "upto_date"=>Carbon::parse(Carbon::now()->format("Y")."-08-14")->format("Y-m-d"),
            "rebates"=>5,            
            "rebates_in_perc"=>true,
            "apply_on_current_tax"=>true,
        ],
    ],
];
