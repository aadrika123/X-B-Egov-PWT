<?php

// Helper made by Sandeep Bara

// function for Static Message

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

if (!function_exists("responseMsg")) {
    function responseMsg($status, $message, $data)
    {
        $response = ['status' => $status, "message" => $message, "data" => $data];
        return response()->json($response, 200);
    }
}

/**
 * | Response Msg Version2 with apiMetaData
 */
if (!function_exists("responseMsgs")) {
    function responseMsgs($status, $msg, $data, $apiId = null, $version = null, $queryRunTime = null, $action = null, $deviceId = null)
    {
        return response()->json([
            'status' => $status,
            'message' => $msg,
            'meta-data' => [
                'apiId' => $apiId,
                'version' => $version,
                'responsetime' => responseTime(),
                'epoch' => Carbon::now()->format('Y-m-d H:i:m'),
                'action' => $action,
                'deviceId' => $deviceId
            ],
            'data' => $data
        ]);
    }
}

/**
 * | To through Validation Error
 */
if (!function_exists("validationError")) {
    function validationError($validator)
    {
        return responseMsg(false, 'Validation Error', $validator->errors()->all());
    }
}

if (!function_exists("validationErrorV2")) {
    function validationErrorV2($validator)
    {
        $response = ['status' => false, "message" => 'Validation Error', "errors" => $validator->errors()];
        return response()->json($response, 200);
    }
}


if (!function_exists("print_var")) {
    function print_var($data = '')
    {
        echo "<pre>";
        print_r($data);
        echo ("</pre>");
    }
}

if (!function_exists("objToArray")) {
    function objToArray(object $data)
    {
        $arrays = $data->toArray();
        return $arrays;
    }
}

if (!function_exists("remove_null")) {
    function remove_null($data, $encrypt = false, array $key = ["id"])
    {
        $collection = collect($data)->map(function ($name, $index) use ($encrypt, $key) {
            if (is_object($name) || is_array($name)) {
                return remove_null($name, $encrypt, $key);
            } else {
                if ($encrypt && (in_array(strtolower($index), array_map(function ($keys) {
                    return strtolower($keys);
                }, $key)))) {
                    return Crypt::encrypt($name);
                } elseif (is_null($name))
                    return "";
                else
                    return $name;
            }
        });
        return $collection;
    }
}

if (!function_exists("ConstToArray")) {
    function ConstToArray(array $data, $type = '')
    {
        $arra = [];
        $retuen = [];
        foreach ($data as $key => $value) {
            $arra['id'] = $key;
            if (is_array($value)) {
                foreach ($value as $keys => $val) {
                    $arra[strtolower($keys)] = $val;
                }
            } else {
                $arra[strtolower($type)] = $value;
            }
            $retuen[] = $arra;
        }
        return $retuen;
    }
}


if (!function_exists("floatRound")) {
    function floatRound(float $number, int $roundUpto = 0)
    {
        return round($number, $roundUpto);
    }
}

// get due date by date
if (!function_exists('calculateQuaterDueDate')) {
    function calculateQuaterDueDate(String $date): String
    {
        /* ------------------------------------------------------------
            * Request
            * ------------------------------------------------------------
            * #reqFromdate
            * ------------------------------------------------------------
            * Calculation
            * ------------------------------------------------------------
            * #MM =         | Get month from reqFromdate
            * #YYYY =       | Get year from reqFromdate
            * #dueDate =    | IF MM >=4 AND MM <=6 THE 
                            |       #YYYY-06-30
                            | IF MM >=7 AND MM <=9 THE 
                            |       #YYYY-09-30
                            | IF MM >=10 AND MM <=12 THE 
                            |       #YYYY-12-31
                            | IF MM >=1 AND MM <=3 THE 
                            |       (#YYYY+1)-03-31
        
        */
        $carbonDate = Carbon::createFromFormat("Y-m-d", $date);
        $MM = (int) $carbonDate->format("m");
        $YYYY = (int) $carbonDate->format("Y");

        if ($MM >= 4 && $MM <= 6) return $YYYY . "-06-30";
        if ($MM >= 7 && $MM <= 9) return $YYYY . "-09-30";
        if ($MM >= 10 && $MM <= 12) return $YYYY . "-12-31";
        if ($MM >= 1 && $MM <= 3) return ($YYYY) . "-03-31";
    }
}
// get due date by date


if (!function_exists('getMonthsByQuarter')) {
    /**
     * Get the start and end date of a specific quarter for a given financial year.
     *
     * @param string $financialYear The financial year in the format "YYYY-YYYY".
     * @param int $quarter The quarter number (1-4).
     * @return string JSON encoded array with the start and end date of the quarter.
     */
    function getMonthsByQuarter(string $financialYear, int $quarter): string
    {
        [$startYear, $endYear] = explode('-', $financialYear);

        // Define quarters with corresponding month numbers
        $quarters = [
            1 => [4, 6],   // Q1: April to June
            2 => [7, 9],   // Q2: July to September
            3 => [10, 12], // Q3: October to December
            4 => [1, 3],   // Q4: January to March (of next year)
        ];

        // Validate the quarter input
        if (!isset($quarters[$quarter])) {
            return json_encode(['error' => 'Invalid quarter']);
        }

        // Get the start and end month of the selected quarter
        [$startMonth, $endMonth] = $quarters[$quarter];

        // Determine the year based on the month
        $startYearForQuarter = $startMonth >= 4 ? $startYear : $endYear;
        $endYearForQuarter = $endMonth >= 4 ? $startYear : $endYear;

        // Get the first day of the quarter
        $startDate = Carbon::createFromDate($startYearForQuarter, $startMonth, 1)->startOfMonth()->format('Y-m-d');
        // Get the last day of the quarter
        $endDate = Carbon::createFromDate($endYearForQuarter, $endMonth, 1)->endOfMonth()->format('Y-m-d');

        // Return the start and end date of the quarter
        return json_encode([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}




// Get Financial Year Due Quarter 
if (!function_exists('readFinancialDueQuarter')) {
    function readFinancialDueQuarter($date)
    {
        // $carbonDate = Carbon::createFromDate("Y-m-d", $date);
        $DD = (int)$date->format("d");
        $MM = (int)$date->format("m");
        $YYYY = (int)$date->format("Y");

        if ($MM <= 4) $MM = 03;

        if ($MM > 4) {
            $MM = 03;
            $YYYY = $YYYY + 1;
        }

        $dueDate = $YYYY . '-' . $MM . '-' . $DD;           // Financial Year Due Date=$YYYY-03-31
        return $dueDate;
    }
}

// Get Quarter Start Date
if (!function_exists('calculateQuarterStartDate')) {
    function calculateQuarterStartDate(String $date): String
    {
        /* ------------------------------------------------------------
            * Request
            * ------------------------------------------------------------
            * #reqFromdate
            * ------------------------------------------------------------
            * Calculation
            * ------------------------------------------------------------
            * #MM =         | Get month from reqFromdate
            * #YYYY =       | Get year from reqFromdate
            * #dueDate =    | IF MM >=4 AND MM <=6 THE 
                            |       #YYYY-06-30
                            | IF MM >=7 AND MM <=9 THE 
                            |       #YYYY-09-30
                            | IF MM >=10 AND MM <=12 THE 
                            |       #YYYY-12-31
                            | IF MM >=1 AND MM <=3 THE 
                            |       (#YYYY+1)-03-31
        
        */
        $carbonDate = Carbon::createFromFormat("Y-m-d", $date);
        $MM = (int) $carbonDate->format("m");
        $YYYY = (int) $carbonDate->format("Y");

        if ($MM >= 4 && $MM <= 6) return $YYYY . "-04-01";
        if ($MM >= 7 && $MM <= 9) return $YYYY . "-07-01";
        if ($MM >= 10 && $MM <= 12) return $YYYY . "-10-01";
        if ($MM >= 1 && $MM <= 3) return ($YYYY) . "-01-01";
    }
}
if (!function_exists('calculatePreviousYearLastQuarterStartDate')) {
    function calculatePreviousYearLastQuarterStartDate(String $date): String
    {
        $carbonDate = Carbon::createFromFormat("Y-m-d", $date);
        $MM = (int) $carbonDate->format("m");
        $YYYY = (int) $carbonDate->format("Y");

        // Check if the current date is in the first quarter of the year
        if ($MM >= 1 && $MM <= 3) {
            return ($YYYY - 1) . "-10-01"; // Return October 1st of the previous year
        }

        // If not in the first quarter, calculate the start date of the current year's last quarter
        $lastQuarterStartDate = Carbon::create($YYYY, 10, 1)->subMonths(3);
        return $lastQuarterStartDate->format("Y-m-d");
    }
}

/**
 * |
 */
if (!function_exists('getFinancialYear')) {
    function getFinancialYear($date)
    {
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        if ($month <= 3) {
            return ($year - 1) . '-' . $year;
        } else {
            return $year . '-' . ($year + 1);
        }
    }
}

// get Financual Year by date
if (!function_exists('calculateQtr')) {
    function calculateQtr(String $date): String
    {
        /* ------------------------------------------------------------
            * Request
            * ------------------------------------------------------------
            * #reqDate
            * ------------------------------------------------------------
            * Calculation
            * ------------------------------------------------------------
            * #MM =         | Get month from reqDate
            * #YYYY =       | Get year from reqDate
            * #qtr =        | IF MM >=4 AND MM <=6 THEN 
                            |       #qtr = 1
                            | IF MM >=7 AND MM <=9 THEN 
                            |       #qtr = 2
                            | IF MM >=10 AND MM <=12 THEN 
                            |       #qtr = 3
                            | IF MM >=1 AND MM <=3 THEN 
                            |       #qtr = 4
        */
        $carbonDate = Carbon::createFromFormat("Y-m-d", $date);
        $MM = (int) $carbonDate->format("m");

        if ($MM >= 4 && $MM <= 6) return 1;
        if ($MM >= 7 && $MM <= 9) return 2;
        if ($MM >= 10 && $MM <= 12) return 3;
        if ($MM >= 1 && $MM <= 3) return 4;
    }
}
// get Financual Year by date
if (!function_exists('calculateFYear')) {
    function calculateFYear(String $date = null): String
    {
        /* ------------------------------------------------------------
            * Request
            * ------------------------------------------------------------
            * #reqDate
            * ------------------------------------------------------------
            * Calculation
            * ------------------------------------------------------------
            * #MM =         | Get month from reqDate
            * #YYYY =       | Get year from reqDate
            * #FYear =      | IF #MM >= 1 AND #MM <=3 THEN 
                            |   #FYear = (#YYYY-1)-#YYYY
                            | IF #MM > 3 THEN 
                            |   #FYear = #YYYY-(#YYYY+1)
        */
        if (!$date) {
            $date = Carbon::now()->format('Y-m-d');
        }
        $carbonDate = Carbon::createFromFormat("Y-m-d", $date);
        $MM = (int) $carbonDate->format("m");
        $YYYY = (int) $carbonDate->format("Y");

        return ($MM <= 3) ? ($YYYY - 1) . "-" . $YYYY : $YYYY . "-" . ($YYYY + 1);
    }
}

if (!function_exists("fromRuleEmplimenteddate")) {
    function fromRuleEmplimenteddate(): String
    {
        /* ------------------------------------------------------------
            * Calculation
            * ------------------------------------------------------------
            * subtract 12 year from current date
        */
        $date =  Carbon::now()->subYear(12)->format("Y");
        return $date . "-04-01";
    }
}
if (!function_exists("FyListasoc")) {
    function FyListasoc($date = null)
    {
        $data = [];
        $strtotime = $date ? strtotime($date) : strtotime(date('Y-m-d'));
        $y = date('Y', $strtotime);
        $m = date('m', $strtotime);
        $year = $y;
        if ($m > 3)
            $year = $y + 1;
        while (true) {
            $data[] = ($year - 1) . '-' . $year;
            if ($year >= date('Y') + 1)
                break;
            ++$year;
        }
        // print_var($data);die;
        return ($data);
    }
}

if (!function_exists('FyListdesc')) {
    function FyListdesc($date = null)
    {
        $data = [];
        $strtotime = $date ? strtotime($date) : strtotime(date('Y-m-d'));
        $y = date('Y', $strtotime);
        $m = date('m', $strtotime);
        $year = $y;
        if ($m > 3)
            $year = $y + 1;
        while (true) {
            $data[] = ($year - 1) . '-' . $year;
            if ($year == '2015')
                break;
            --$year;
        }
        // print_var($data);die;
        return ($data);
    }
}
if (!function_exists('getFY')) {
    function getFY($date = null)
    {
        if (is_null($date)) {
            $carbonDate = Carbon::now(); //createFromFormat("Y-m-d", $date);
            $MM = (int) $carbonDate->format("m");
            $YY = (int) $carbonDate->format("Y");
            // $MM = date("m");
            // $YY = date("Y");

        } else {

            $MM = date("m", strtotime($date));
            $YY = date("Y", strtotime($date));
        }
        if ($MM > 3) {
            return ($YY) . "-" . ($YY + 1);
        } else {
            return ($YY - 1) . "-" . ($YY);
        }
    }
}

if (!function_exists("FyearFromUptoDate")) {
    function FyearFromUptoDate($fyear = null)
    {
        if (!$fyear)
            $fyear = getFY();
        list($fromYear, $uptoYear) = explode("-", $fyear);
        return [$fromYear . "-04-01", $uptoYear . "-03-31"];
    }
}

if (!function_exists('eloquentItteration')) {
    function eloquentItteration($a, $model)
    {
        $arr = [];
        foreach ($a as $key => $as) {
            $pieces = preg_split('/(?=[A-Z])/', $key);           // for spliting the variable by its caps value
            $p = implode('_', $pieces);                            // Separating it by _ 
            $final = strtolower($p);                              // converting all in lower case
            $c = $model . '->' . $final . '=' . "$as" . ';';              // Creating the Eloquent
            array_push($arr, $c);
        }
        return $arr;
    }
}

/**
 * | format the Decimal in Round Figure
 * | Created On-24-07-2022 
 * | Created By-Anshu Kumar
 * | @var number the number to be round
 * | @return @var round
 */
if (!function_exists('roundFigure')) {
    function roundFigure(float $number)
    {
        $round = round($number, 2);
        return number_format((float)$round, 2, '.', '');
    }
}

if (!function_exists('getIndianCurrency')) {
    function getIndianCurrency(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety'
        );
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str[] = null;
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
        return 'Rupee ' . ($Rupees ? $Rupees  : 'Rupee Zero') . $paise;
    }
}

if (!function_exists('getNumberToSentence')) {
    function getNumberToSentence(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            0 => 'Zero',
            1 => 'First',
            2 => 'Second',
            3 => 'Third',
            4 => 'Fourth',
            5 => 'Fifth',
            6 => 'Sixth',
            7 => 'Seventh',
            8 => 'Eighth',
            9 => 'Ninth',
            10 => 'Tenth',
            11 => 'Eleventh',
            12 => 'Twelve',
            13 => 'Thirteenth',
            14 => 'Fourteenth',
            15 => 'Fifteenth',
            16 => 'Sixteenth',
            17 => 'Seventeenth',
            18 => 'Eighteenth',
            19 => 'Nineteenth',
            20 => 'Twentieth',
            30 => 'Thirtieth',
            40 => 'Fortieths',
            50 => 'Fiftieth',
            60 => 'Sixtieth',
            70 => 'Seventieth',
            80 => 'Eightieth',
            90 => 'Ninetieth'
        );
        $swords = array(
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety'
        );
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        if ($number > 99) {
            $words = $swords;
        }
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str[] = null;
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? "Pont" . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' ' : '';
        return ($Rupees) . $paise;
    }
}

// Decimal to SqMt Conversion
if (!function_exists('decimalToSqMt')) {
    function decimalToSqMt(float $num)
    {
        $num = $num * 40.50;
        return $num;
    }
}

// Decimal to SqMt Conversion
if (!function_exists('decimalToSqFt')) {
    function decimalToSqFt(float $num)
    {
        $num = $num * 435.6;
        $num = number_format((float)$num, 2, '.', '');
        return $num;
    }
}

// sqFt to SqMt Conversion
if (!function_exists('sqFtToSqMt')) {
    function sqFtToSqMt(float $num)
    {
        $num = $num * 0.09290304;
        // $num = number_format((float)$num, 2, '.', '');
        return $num;
    }
}

// Decimal to Acre Conversion
if (!function_exists('decimalToAcre')) {
    function decimalToAcre(float $num)
    {
        $num = $num / 100;
        return $num;
    }

    // getClientIpAddress
    if (!function_exists('getClientIpAddress')) {
        function getClientIpAddress()
        {
            // Get real visitor IP behind CloudFlare network
            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }

            // Sometimes the `HTTP_CLIENT_IP` can be used by proxy servers
            $ip = @$_SERVER['HTTP_CLIENT_IP'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }

            // Sometimes the `HTTP_X_FORWARDED_FOR` can contain more than IPs 
            $forward_ips = @$_SERVER['HTTP_X_FORWARDED_FOR'];
            if ($forward_ips) {
                $all_ips = explode(',', $forward_ips);

                foreach ($all_ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }

            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

// get days from two dates
if (!function_exists('dateDiff')) {
    function dateDiff(string $date1, string $date2)
    {
        $date1 = Carbon::parse($date1);
        $date2 = Carbon::parse($date2);

        return $date1->diffInDays($date2);
    }
}

// Get Authenticated users list
if (!function_exists('authUser')) {
    function authUser($req)
    {
        $auth = $req->auth;
        if (!$auth)
            throw new Exception("Auth Not Available");
        if (is_array($auth))
            return (object)$auth;
        else
            return json_decode($req->auth);
    }
}

/**
 * | Flip Constants
 */
if (!function_exists('flipConstants')) {
    function flipConstants($constant)
    {
        $chunk = collect($constant)->chunk(1);
        $flip = $chunk->map(function ($a) {
            return collect($a)->flip();
        });

        $flip = $flip->collapse();
        return $flip;
    }

    function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

/**
 * | Api Response time for the the apis
 */

if (!function_exists("responseTime")) {
    function responseTime()
    {
        $responseTime = (microtime(true) - LARAVEL_START) * 1000;
        return round($responseTime, 2);
    }


    /**
     * | Convert YMD format to dmy
     */
    if (!function_exists("ymdToDmyDate")) {
        function ymdToDmyDate($date)
        {
            if (isset($date)) {
                $parsedDate = Carbon::parse($date)->format('d-m-Y');
                return $parsedDate;
            }
        }
    }


    /**
     * | Check if the floor have same usage types
     */
    if (!function_exists("isElementsSame")) {
        function isElementsSame($elements)
        {
            $firstElement = $elements->first();
            $same = true;
            foreach ($elements as $element) {
                if ($element != $firstElement) {
                    $same = false;
                    break;
                }
            }
            return $same;
        }
    }

    // checks if the number lies in between
    if (!function_exists('is_between')) {
        function is_between($number, $min, $max, $inclusive = true)
        {
            if ($inclusive)
                return $number >= $min && $number <= $max;
            else
                return $number > $min && $number < $max;
        }
    }



    /**
     * | Search Filter for Shop Rental Data
     */

    if (!function_exists("searchShopRentalFilter")) {
        function searchShopRentalFilter($orm, $req)
        {
            $key = trim($req->key);
            return $orm->where(function ($query) use ($key) {
                $query->orwhere('shop_no', 'ILIKE', '%' . $key . '%')
                    ->orwhere("allottee", 'ILIKE', '%' . $key . '%');
                // ->orwhere("vendor_name", 'ILIKE', '%' . $key . '%')
                // ->orwhere("toll_no", 'ILIKE', '%' . $key . '%');
            });
        }
    }



    /**
     * | Search Filter for Shop Rental Data
     */

    if (!function_exists("searchTollRentalFilter")) {
        function searchTollRentalFilter($orm, $req)
        {
            $key = trim($req->key);
            return $orm->where(function ($query) use ($key) {
                // $query->orwhere('shop_no', 'ILIKE', '%' . $key . '%')
                // ->orwhere("allottee", 'ILIKE', '%' . $key . '%');
                $query->orwhere("vendor_name", 'ILIKE', '%' . $key . '%')
                    ->orwhere("toll_no", 'ILIKE', '%' . $key . '%');
            });
        }
    }


    /**
     * | Api Response time for the the apis
     */

    if (!function_exists("paginator")) {
        function paginator($orm, $req)
        {
            $perPage = $req->perPage ? $req->perPage :  10;
            $paginator = $orm->paginate($perPage);
            return [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
        }
    }


    /**
     * | All Data Filter According to Key
     */

    if (!function_exists("searchFilter")) {
        function searchFilter($orm, $req)
        {
            $key = trim($req->key);
            return $orm->where(function ($query) use ($key) {
                $query->orwhere('application_no', 'ILIKE', '%' . $key . '%')
                    ->orwhere("applicant", 'ILIKE', '%' . $key . '%')
                    // ->orwhere(DB::raw("TO_CHAR(application_date, 'DD/MM/YYYY')"), 'ILIKE', '%' . $key . '%')
                    ->orwhere("entity_name", 'ILIKE', '%' . $key . '%');
            });
        }
    }
}
