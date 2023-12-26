<?php

/**
 * | Created On-06-10-2022 
 * | Created By-Sandeep Bara
 * | For Trade Licence
 */

return [
    "CITIZEN"       => "Citizen",
    "TRADE_REF_TABLE" => "active_trade_licences",
    "TRADE_RELATIVE_PATH" => "Uploads/Trade",

    "TRADE_NOTICE_REF_TABLE" => "active_trade_notice_consumer_dtls",
    "TRADE_NOTICE_RELATIVE_PATH" => "Uploads/Trade/Notice",

    "APPLICATION-TYPE" =>
    [
        "NEWLICENSE"    =>  "1",
        "RENEWAL"       =>  "2",
        "AMENDMENT"     =>  "3",
        "SURRENDER"     =>  "4"
    ],
    "APPLICATION-TYPE-BY-ID" =>
    [
        "1" =>  "NEW LICENSE",
        "2" =>  "RENEWAL",
        "3" =>  "AMENDMENT",
        "4" =>  "SURRENDER"
    ],
    "USER-TYPE-SHORT-NAME" =>
    [
        ""                 =>"ONLINE",
        "SUPER ADMIN"       =>  "SUPER ADMIN",
        "ADMIN"             =>  "ADMIN",
        "PROJECT MANAGER"   =>  "PM",
        "PM"                =>  "PM",
        "Team Leader"       =>  "TL",
        "TL"                =>  "TL",
        "JUN SUWIDHA KENDRA" =>  "JSK",
        "JSK"               =>  "JSK",
        "BACK OFFICE"       =>  "BO",
        "BO"                =>  "BO",
        "DEALING ASSISTANT" => "DA",
        "DA"                =>  "DA",
        "ULB TAX COLLECTOR" =>  "UTC",
        "UTC"               =>  "UTC",
        "AJENCY TAX COLLECTOR" => "TC",
        "TAX COLLECTOR"     =>  "TC",
        "TC"                =>  "TC",
        "ATC"               =>  "TC",
        "SECTION INCHARGE"  =>  "SI",
        "SI"                =>  "SI",
        "ASSISTANT TAX SUPRITENDENT" =>  "SI",
        "SECTION HEAD"      =>  "SH",
        "SH"                =>  "SH",
        "TAX DAROGA"        =>  "TD",
        "TD"                =>  "TD",
        "JUNIOR ENGINEER"   =>  "JE",
        "JE"                =>  "JE",
        "ASSISTANT ENGINEER" => "AE",
        "AE"                =>  "AE",
        "EXECUTIVE OFFICER" =>  "EO",
        "EO"                =>  "EO",
        "LIPIK"             =>  "LP",
        "LP"                =>  "LP",
        "SENIOUR LIPIK"     =>  "SRLP",
        "SENIOR LIPIK"    =>  "SRLP",
        "SRLP"              =>  "SRLP",
        "TAX SUPERITENDENT"  =>  "TS", #EO
        "TS"                =>  "TS",
        "DEPUTY MUNICIPAL COMMISSIONER"=>"DMC",
        "DMC"               => "DMC",
        "FIELD ENGINEER"    => "FE", #TC
        "FE"                => "FE" #TC

    ],
    
    "CANE-NO-HAVE-WARD"=>["ONLINE", "JSK","BO" ,"PM","SUPER ADMIN", "TL"],
    "CANE-APPLY-APPLICATION"=>["ONLINE", "JSK", "SRLP","UTC", "FE","TC", "SUPER ADMIN", "TL"],
    "CANE-CUTE-PAYMENT"=>["JSK", "SRLP","UTC","FE", "TC", "SUPER ADMIN", "TL"],
    "VERIFICATION-STATUS"=>
    [
        "PENDING"   => 0,
        "VERIFY"    => 1,
        "BTC"       => 2,
        "REJECT"    => 3,
        "BACKWARD"  => 4,
        "APROVE"    => 5,
    ],
];
