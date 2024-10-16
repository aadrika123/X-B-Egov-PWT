<?php

namespace App\Http\Controllers\Water;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Water\WaterTran;
use App\Traits\Water\WaterTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Water\WaterConsumer as WaterWaterConsumer;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterConsumer;
use Illuminate\Support\Facades\Config;
use App\Models\Water\WaterConsumerDemand;
use Illuminate\Support\Facades\Validator;
use App\Models\Water\WaterSecondConsumer;
use  App\Http\Requests\Water\colllectionReport;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Advertisements\RefRequiredDocument;
use App\Models\Workflows\WfActiveDocument;
use App\Models\CustomDetail;
use App\Models\Water\WaterConsumerOwner;
use App\Models\WaterTempDisconnection;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\Water\Concrete\Report;
use App\Repository\Water\Interfaces\IConsumer;
use App\Traits\Workflow\Workflow;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Collection;
use App\MicroServices\DocUpload;
use App\Models\Property\ZoneMaster;
use App\Models\User;
use App\Models\Water\WaterConsumerDemandRecord;
use App\Models\Workflows\WfRoleusermap;

/**
 * | ----------------------------------------------------------------------------------
 * | Water Module |
 * |-----------------------------------------------------------------------------------
 * | Created On-14-04-2023
 * | Created By-Sam Kumar 
 * | Created For-Water Related Reports
 */

class WaterReportController extends Controller
{
    use WaterTrait;
    use Workflow;

    private $Repository;
    private $ReportRepository;
    private $_docUrl;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct(IConsumer $Repository)
    {
        $this->Repository = $Repository;
        $this->ReportRepository = App::makeWith(Report::class);
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_docUrl = Config::get("waterConstaint.DOC_URL");
    }
    /**
     * | Water count of online payment
        | Serial No : 01
        | Not Tested
     */
    public function onlinePaymentCount(Request $req)
    {
        try {
            $mWaterTran = new WaterTran();
            $year = Carbon::now()->year;

            if (isset($req->fyear))
                $year = substr($req->fyear, 0, 4);

            $financialYearStart = $year;
            if (Carbon::now()->month < 4) {
                $financialYearStart--;
            }

            $fromDate =  $financialYearStart . '-04-01';
            $toDate   =  $financialYearStart + 1 . '-03-31';

            if ($req->financialYear) {
                $fy = explode('-', $req->financialYear);
                $strtYr = collect($fy)->first();
                $endYr = collect($fy)->last();
                $fromDate =  $strtYr . '-04-01';
                $toDate   =  $endYr . '-03-31';;
            }

            $waterTran = $mWaterTran->getOnlineTrans($fromDate, $toDate)->get();
            $returnData = [
                'waterCount' => $waterTran->count(),
                'totaAmount' => collect($waterTran)->sum('amount')
            ];

            return responseMsgs(true, "Online Payment Count", remove_null($returnData), "", '', '01', '.ms', 'Post', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Water DCB
        | Serial No : 02
        | Not Working
     */
    public function waterDcb(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            $fromDate = $fromYear . "-04-01";
            $uptoDate = $toYear . "-03-31";
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;


            $from = "
                FROM (
                    SELECT *
                    FROM water_consumers
                    WHERE water_consumers.ulb_id = $ulbId
                    ORDER BY id
                    limit $limit offset $offset
                  )water_consumers
                LEFT JOIN (
                    SELECT STRING_AGG(applicant_name, ', ') AS owner_name,
                        STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                        water_consumers.id AS water_id
                    FROM water_approval_applicants 
                    JOIN (
                        SELECT * 
                        FROM water_consumers
                        WHERE water_consumers.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )water_consumers ON water_consumers.apply_connection_id = water_approval_applicants.application_id
                        AND water_consumers.ulb_id = $ulbId
                    WHERE water_approval_applicants.status = true
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                    GROUP BY water_consumers.id
                )water_owner_detail ON water_owner_detail.application_id = water_consumers.apply_connection_id
                LEFT JOIN (
                    SELECT water_consumer_demands.consumer_id,
                        SUM(
                                CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <='$uptoDate' then water_consumer_demands.amount
                                    ELSE 0
                                    END
                        ) AS current_demand,
                        SUM(
                            CASE WHEN water_consumer_demands.demand_from <'$fromDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_demand,
                    SUM(water_consumer_demands.amount) AS total_demand
                    FROM water_consumer_demands
                    JOIN (
                        SELECT * 
                        FROM water_consumers
                        WHERE water_consumers.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id = $ulbId
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                        AND water_consumer_demands.demand_upto <= '$uptoDate'
                    GROUP BY water_consumer_demands.consumer_id    
                )demands ON demands.consumer_id = water_consumers.id
                LEFT JOIN (
                    SELECT water_consumer_demands.consumer_id,
                        SUM(
                                CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <='$uptoDate' then water_consumer_demands.amount
                                    ELSE 0
                                    END
                        ) AS current_collection,
                        SUM(
                            CASE when water_consumer_demands.demand_from < '$fromDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_collection,
                    SUM(water_consumer_demands.amount) AS total_collection
                    FROM water_consumer_demands
                    JOIN (
                        SELECT * 
                        FROM water_consumers
                        WHERE water_consumers.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                    JOIN water_tran_details ON water_tran_details.related_id = water_consumer_demands.id 
                        AND water_tran_details.related_id is not null 
                    JOIN water_trans ON water_trans.id = water_tran_details.tran_id 
                        AND water_trans.status in (1,2) 
                        AND water_trans.related_id is not null
                        AND water_trans.tran_type = 'Demand Collection'
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                        AND water_trans.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND water_consumer_demands.demand_upto <='$uptoDate'
                    GROUP BY water_consumer_demands.consumer_id
                )collection ON collection.consumer_id = water_consumers.id
                LEFT JOIN ( 
                    SELECT water_consumer_demands.consumer_id,
                    SUM(water_consumer_demands.amount) AS total_prev_collection
                    FROM water_consumer_demands
                    JOIN (
                        SELECT * 
                        FROM water_consumers
                        WHERE water_consumers.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                    JOIN water_tran_details ON water_tran_details.application_id = water_consumer_demands.id 
                        AND water_tran_details.application_id is not null 
                    JOIN water_trans ON water_trans.id = water_tran_details.tran_id 
                        AND water_trans.status in (1,2) 
                        AND water_trans.related_id is not null
                        AND water_trans.tran_type = 'Demand Collection'
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id = $ulbId
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                        AND water_trans.tran_date < '$fromDate'
                    GROUP BY water_consumer_demands.consumer_id
                )prev_collection ON prev_collection.consumer_id = water_consumers.id 
                JOIN ulb_ward_masters ON ulb_ward_masters.id = water_consumers.ward_mstr_id
                WHERE  water_consumers.ulb_id = $ulbId  
                    " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "           
            ";
            $footerfrom = "
                FROM water_consumers
                LEFT JOIN (
                    SELECT STRING_AGG(applicant_name, ', ') AS owner_name,
                        STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                        water_consumers.id AS water_id
                    FROM water_approval_applicants 
                    JOIN water_consumers ON water_consumers.apply_connection_id = water_approval_applicants.application_id
                        AND water_consumers.ulb_id = $ulbId
                    WHERE water_approval_applicants.status = true
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                    GROUP BY water_consumers.id
                )water_owner_detail ON water_owner_detail.application_id = water_consumers.apply_connection_id
                LEFT JOIN (
                    SELECT water_consumer_demands.consumer_id,
                        SUM(
                                CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <='$uptoDate' then water_consumer_demands.amount
                                    ELSE 0
                                    END
                        ) AS current_demand,
                        SUM(
                            CASE WHEN water_consumer_demands.demand_from < '$fromDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_demand,
                    SUM(water_consumer_demands.amount) AS total_demand
                    FROM water_consumer_demands
                    JOIN water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                        AND water_consumer_demands.demand_upto <= '$uptoDate'
                    GROUP BY water_consumer_demands.consumer_id    
                )demands ON demands.consumer_id = water_consumers.id
                LEFT JOIN (
                    SELECT water_consumer_demands.consumer_id,
                        SUM(
                                CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <='$uptoDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                        ) AS current_collection,
                        SUM(
                            CASE WHEN water_consumer_demands.demand_from < '$fromDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_collection,
                    SUM(water_consumer_demands.amount) AS total_collection
                    FROM water_consumer_demands
                    JOIN water_consumers ON water_consumers.id = water_consumer_demands.consumer_id

                                #####################------------#################

                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN water_trans ON water_trans.id = prop_tran_dtls.tran_id 
                        AND water_trans.status in (1,2) AND water_trans.related_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND water_trans.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.due_date<='$uptoDate'
                    GROUP BY prop_demands.related_id
                )collection ON collection.related_id = prop_properties.id
                LEFT JOIN ( 
                    SELECT prop_demands.related_id,
                    SUM(prop_demands.amount) AS total_prev_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.related_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN water_trans ON water_trans.id = prop_tran_dtls.tran_id 
                        AND water_trans.status in (1,2) AND water_trans.related_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND water_trans.tran_date<'$fromDate'
                    GROUP BY prop_demands.related_id
                )prev_collection ON prev_collection.related_id = prop_properties.id 
                JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                WHERE  prop_properties.ulb_id = $ulbId  
                    " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "           
            ";
            $select = "SELECT  prop_properties.id,
                            ulb_ward_masters.ward_name AS ward_no,
                            CONCAT('', prop_properties.holding_no, '') AS holding_no,
                            (
                                CASE WHEN prop_properties.new_holding_no='' OR prop_properties.new_holding_no IS NULL THEN 'N/A' 
                                ELSE prop_properties.new_holding_no END
                            ) AS new_holding_no,
                            prop_owner_detail.owner_name,
                            prop_owner_detail.mobile_no,
                    
                            COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            ) AS arrear_demand,
                            COALESCE(demands.current_demand, 0::numeric) AS current_demand,   
                            COALESCE(prev_collection.total_prev_collection, 0::numeric) AS previous_collection,
                            
                            COALESCE(collection.arrear_collection, 0::numeric) AS arrear_collection,
                            COALESCE(collection.current_collection, 0::numeric) AS current_collection,
                    
                            (COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) )AS old_due,
                    
                            (COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric)) AS current_due,
                    
                            (
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.current_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            ) AS outstanding                                 
            ";
            $footerselect = "SELECT
                        COUNT(prop_properties.id)AS total_prop,
                        COUNT(DISTINCT(ulb_ward_masters.ward_name)) AS total_ward,
                        SUM(
                            COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            )
                        ) AS outstanding_at_begin,
                        SUM(COALESCE(demands.currend_demand, 0::numeric)) AS current_demand,
                
                        SUM(COALESCE(prev_collection.total_prev_collection, 0::numeric)) AS prev_coll,
                        SUM(COALESCE(collection.arrear_collection, 0::numeric)) AS arrear_ollection,
                        SUM(COALESCE(collection.current_collection, 0::numeric)) AS current_collection,
                
                        SUM(
                            (
                                COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) 
                            )
                        )AS old_due,
                
                        SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric))) AS current_due,
                
                        SUM(
                            (
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.currend_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            )
                        ) AS outstanding               
            ";
            $data = DB::TABLE(DB::RAW("($select $from)AS prop"))->get();
            // $footer = DB::TABLE(DB::RAW("($footerselect $footerfrom)AS prop"))->get();
            $items = $data;
            $total = (collect(DB::SELECT("SELECT COUNT(*) AS total $footerfrom"))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                // "perPage" => $perPage,
                // "page" => $page,
                // "items" => $items,
                // "footer" => $footer,
                // "total" => $total,
                // "numberOfPages" => $numberOfPages,
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage - 1
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | Ward Wise Dcb 
        | Serial No : 03
        | Working
        | Not Verified
     */
    public function wardWiseDCB(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "connectionType" => "nullable|in:1,0",
                "propType" => "nullable|in:1,2,3"
                // "page" => "nullable|digits_between:1,9223372036854775807",
                // "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $request->request->add(["metaData" => ["", 1.1, "", $request->getMethod(), $request->deviceId,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $connectionType = null;
            $propType = null;
            $refPropType = Config::get('waterConstaint.PROPERTY_TYPE');

            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            $fromDate = $fromYear . "-04-01";
            $uptoDate = $toYear . "-03-31";
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->connectionType != '') {
                $connectionType = $request->connectionType;
            }
            if ($request->propType) {
                switch ($request->propType) {
                    case ('1'):
                        $propType = $refPropType['Residential'];
                        break;
                    case ('2'):
                        $propType = $refPropType['Commercial'];
                        break;
                    case ('3'):
                        $propType = $refPropType['Government'];
                        break;
                }
            }

            # From Querry
            //" . ($connectionType ? " AND water_consumer_demands.connection_type IN (" . implode(',', $connectionType) . ")" : "") . "
            $from = "
                FROM ulb_ward_masters 
                LEFT JOIN(
                        SELECT water_consumers.ward_mstr_id,
                        COUNT
                        (DISTINCT (
                            CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <= '$uptoDate'  then water_consumer_demands.consumer_id
                            END)
                        ) as current_demand_consumer,
                        SUM(
                                CASE WHEN  water_consumer_demands.demand_from >= '$fromDate' 
                                        AND water_consumer_demands.demand_upto <= '$uptoDate'  then water_consumer_demands.amount
                                ELSE 0
                                    END
                        ) AS current_demand,
                        COUNT
                        (DISTINCT (
                            CASE WHEN water_consumer_demands.demand_from < '$fromDate'  then water_consumer_demands.consumer_id
                            END)
                        ) as arrear_demand_consumer,
                        SUM(
                            CASE WHEN water_consumer_demands.demand_from < '$fromDate'  then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_demand,
                        SUM(water_consumer_demands.amount) AS total_demand
                FROM water_consumer_demands
                JOIN water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                JOIN (
                    SELECT water_consumer_meters.* 
                    FROM water_consumer_meters
                        JOIN(
                            select max(id)as max_id
                            from water_consumer_meters
                            where status = 1
                            group by consumer_id
                        )maxdata on maxdata.max_id = water_consumer_meters.id
                    )water_consumer_meters on water_consumer_meters.consumer_id = water_consumers.id

                WHERE water_consumer_demands.status = true
                    AND water_consumer_demands.ulb_id = $ulbId
                    " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                    " . ($connectionType ? " AND water_consumer_meters.meter_status = $connectionType " : "") . "
                    " . ($propType ? " AND water_consumers.property_type_id = $propType" : "") . "
                    AND water_consumer_demands.demand_upto <='$uptoDate'
                GROUP BY water_consumers.ward_mstr_id
                )demands ON demands.ward_mstr_id = ulb_ward_masters.id
                LEFT JOIN (
                    SELECT water_consumers.ward_mstr_id,
                    COUNT
                        (DISTINCT (
                            CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <= '$uptoDate'  then water_consumer_demands.consumer_id
                            END)
                        ) as current_collection_consumer,

                        COUNT(DISTINCT(water_consumers.id)) AS collection_from_no_of_consumer,
                        SUM(
                                CASE WHEN water_consumer_demands.demand_from >= '$fromDate' 
                                AND water_consumer_demands.demand_upto <= '$uptoDate'  then water_consumer_demands.amount
                                    ELSE 0
                                    END
                        ) AS current_collection,

                        COUNT
                            (DISTINCT (
                                CASE WHEN water_consumer_demands.demand_from < '$fromDate' then water_consumer_demands.consumer_id
                                END)
                            ) as arrear_collection_consumer,

                        SUM(
                            CASE when water_consumer_demands.demand_from < '$fromDate' then water_consumer_demands.amount
                                ELSE 0
                                END
                            ) AS arrear_collection,
                            
                    SUM(water_consumer_demands.amount) AS total_collection
                    FROM water_consumer_demands
                    JOIN water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                    JOIN water_tran_details ON water_tran_details.demand_id = water_consumer_demands.id 
                        AND water_tran_details.demand_id is not null 
                    JOIN water_trans ON water_trans.id = water_tran_details.tran_id 
                        AND water_trans.status in (1,2) 
								AND water_trans.related_id is not null
								AND water_trans.tran_type = 'Demand Collection'
                    JOIN (
                        SELECT water_consumer_meters.* from water_consumer_meters
                            join(
                                select max(id)as max_id
                                from water_consumer_meters
                                where status = 1
                                group by consumer_id
                            )maxdata on maxdata.max_id = water_consumer_meters.id
                        )water_consumer_meters on water_consumer_meters.consumer_id = water_consumers.id            
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id = $ulbId
                        " . ($wardId ? " AND water_consumers.ward_mstr_id = $wardId" : "") . "
                        " . ($connectionType ? " AND water_consumer_meters.meter_status = $connectionType " : "") . "
                        " . ($propType ? " AND water_consumers.property_type_id = $propType" : "") . "
                        AND water_trans.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND water_consumer_demands.demand_from <='$fromDate'
                    GROUP BY (water_consumers.ward_mstr_id)
                )collection ON collection.ward_mstr_id = ulb_ward_masters.id
                LEFT JOIN ( 
                    SELECT water_consumers.ward_mstr_id, 
                        SUM(water_consumer_demands.amount) 
                                AS total_prev_collection
                                FROM water_consumer_demands
                        JOIN water_consumers ON water_consumers.id = water_consumer_demands.consumer_id
                        JOIN water_tran_details ON water_tran_details.demand_id = water_consumer_demands.id
                            AND water_tran_details.demand_id IS NOT NULL
                        JOIN water_trans ON water_trans.id = water_tran_details.tran_id 
                            AND water_trans.status in (1,2) 
                            AND water_trans.related_id IS NOT NULL
                            AND water_trans.tran_type = 'Demand Collection'
                        JOIN (
                            SELECT water_consumer_meters.* from water_consumer_meters
                                join(
                                    select max(id)as max_id
                                    from water_consumer_meters
                                    where status = 1
                                    group by consumer_id
                                )maxdata on maxdata.max_id = water_consumer_meters.id
                            )water_consumer_meters on water_consumer_meters.consumer_id = water_consumers.id    
                    WHERE water_consumer_demands.status = true 
                        AND water_consumer_demands.ulb_id = $ulbId
                        " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                        " . ($connectionType ? " AND water_consumer_meters.meter_status = $connectionType " : "") . "
                        " . ($propType ? " AND water_consumers.property_type_id = $propType" : "") . "
                       AND water_trans.tran_date <'$fromDate'
                    GROUP BY water_consumers.ward_mstr_id  
                )prev_collection ON prev_collection.ward_mstr_id = ulb_ward_masters.id                 
                WHERE  ulb_ward_masters.ulb_id = $ulbId  
                    " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                GROUP BY ulb_ward_masters.ward_name           
            ";

            # Select Querry
            $select = "SELECT ulb_ward_masters.ward_name AS ward_no, 
                            SUM(COALESCE(demands.current_demand_consumer, 0::numeric)) AS current_demand_consumer,   
                            SUM(COALESCE(demands.arrear_demand_consumer, 0::numeric)) AS arrear_demand_consumer,
                            SUM(COALESCE(collection.current_collection_consumer, 0::numeric)) AS current_collection_consumer,   
                            SUM(COALESCE(collection.arrear_collection_consumer, 0::numeric)) AS arrear_collection_consumer,
                            SUM(COALESCE(collection.collection_from_no_of_consumer, 0::numeric)) AS collection_from_consumer,
                            
                            round(SUM(((collection.arrear_collection_consumer ::numeric) / (case when demands.arrear_demand_consumer > 0 then demands.arrear_demand_consumer else 1 end))*100)) AS arrear_consumer_eff,
                            round(SUM(((collection.current_collection_consumer ::numeric) / (case when demands.current_demand_consumer > 0 then demands.current_demand_consumer else 1 end))*100)) AS current_consumer_eff,

                            round(SUM(COALESCE(
                                COALESCE(demands.current_demand_consumer, 0::numeric) 
                                - COALESCE(collection.collection_from_no_of_consumer, 0::numeric), 0::numeric
                            ))) AS balance_consumer,                       
                            round(SUM(COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            ))) AS arrear_demand,
                    
                            round(SUM(COALESCE(prev_collection.total_prev_collection, 0::numeric))) AS previous_collection,
                            round(SUM(COALESCE(demands.current_demand, 0::numeric))) AS current_demand,
                            round(SUM(COALESCE(collection.arrear_collection, 0::numeric))) AS arrear_collection,
                            round(SUM(COALESCE(collection.current_collection, 0::numeric))) AS current_collection,
                    
                            round(SUM((COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) 
                                )))AS old_due,
                    
                            round(SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric)))) AS current_due,

                            round(SUM((COALESCE(demands.current_demand_consumer, 0::numeric) - COALESCE(collection.current_collection_consumer, 0::numeric)))) AS current_balance_consumer,
                            round(SUM((COALESCE(demands.arrear_demand_consumer, 0::numeric) - COALESCE(collection.arrear_collection_consumer, 0::numeric)))) AS arrear_balance_consumer,

                            round(SUM(((collection.arrear_collection ::numeric) / (case when demands.arrear_demand > 0 then demands.arrear_demand else 1 end))*100)) AS arrear_eff,
                            round(SUM(((collection.current_collection ::numeric) / (case when demands.current_demand > 0 then demands.current_demand else 1 end))*100)) AS current_eff,

                            round(SUM((
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.current_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            ))) AS outstanding                                 
            ";
            // dd($connectionType);
            # Data Structuring
            $dcb = DB::select($select . $from);
            $data['total_arrear_demand']                = round(collect($dcb)->sum('arrear_demand'), 0);
            $data['total_current_demand']               = round(collect($dcb)->sum('current_demand'), 0);
            $data['total_arrear_collection']            = round(collect($dcb)->sum('arrear_collection'), 0);
            $data['total_current_collection']           = round(collect($dcb)->sum('current_collection'), 0);
            $data['total_old_due']                      = round(collect($dcb)->sum('old_due'), 0);
            $data['total_current_due']                  = round(collect($dcb)->sum('current_due'), 0);
            $data['total_arrear_demand_consumer']       = round(collect($dcb)->sum('arrear_demand_consumer'), 0);
            $data['total_current_demand_consumer']      = round(collect($dcb)->sum('current_demand_consumer'), 0);
            $data['total_arrear_collection_consumer']   = round(collect($dcb)->sum('arrear_collection_consumer'), 0);
            $data['total_current_collection_consumer']  = round(collect($dcb)->sum('current_collection_consumer'), 0);
            $data['total_arrear_balance_consumer']      = round(collect($dcb)->sum('arrear_balance_consumer'));
            $data['total_current_balance_consumer']     = round(collect($dcb)->sum('current_balance_consumer'));
            $data['total_current_eff']                  = ($data['total_current_collection_consumer'] == 0) ? 0 : round(($data['total_current_collection_consumer'] / $data['total_current_demand']) * 100);
            $data['total_arrear_consumer_eff']          = ($data['total_arrear_demand_consumer'] == 0) ? 0 : round(($data['total_arrear_collection_consumer'] /  $data['total_arrear_demand_consumer']) * 100);
            $data['total_current_consumer_eff']         = ($data['total_current_demand_consumer'] == 0) ? 0 : round(($data['total_current_collection_consumer']) / ($data['total_current_demand_consumer']) * 100);
            $data['total_arrear_eff']                   = ($data['total_arrear_collection'] == 0) ? 0 : round(($data['total_arrear_collection']) / ($data['total_arrear_demand']) * 100);
            $data['total_eff']                          = round((($data['total_arrear_collection'] + $data['total_current_collection']) / ($data['total_arrear_demand'] + $data['total_current_demand'])) * 100);
            $data['dcb']                                = $dcb;

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    /**
     * | DCB Pie Chart
        | Serial No : 04
        | Working
     */
    public function dcbPieChart(Request $request)
    {
        try {
            $ulbId = $request->ulbId ?? authUser($request)->ulb_id;
            $currentDate = Carbon::now()->format('Y-m-d');
            $currentYear = Carbon::now()->year;
            $currentFyear = getFinancialYear($currentDate);
            $startOfCurrentYear = Carbon::createFromDate($currentYear, 4, 1);   // Start date of current financial year
            $startOfPreviousYear = $startOfCurrentYear->copy()->subYear();      // Start date of previous financial year
            $previousFinancialYear = getFinancialYear($startOfPreviousYear);
            $startOfprePreviousYear = $startOfCurrentYear->copy()->subYear()->subYear();
            $prePreviousFinancialYear = getFinancialYear($startOfprePreviousYear);


            # common function
            $refDate = $this->getFyearDate($currentFyear);
            $fromDate = $refDate['fromDate'];
            $uptoDate = $refDate['uptoDate'];

            # common function
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];

            # common function
            $refDate = $this->getFyearDate($prePreviousFinancialYear);
            $prePreviousFromDate = $refDate['fromDate'];
            $prePreviousUptoDate = $refDate['uptoDate'];


            return $sql1 = $this->demandByFyear($currentFyear, $fromDate, $uptoDate, $ulbId);
            $sql2 = $this->demandByFyear($previousFinancialYear, $previousFromDate, $previousUptoDate, $ulbId);
            $sql3 = $this->demandByFyear($prePreviousFinancialYear, $prePreviousFromDate, $prePreviousUptoDate, $ulbId);

            $currentYearDcb     = DB::select($sql1);
            $previousYearDcb    = DB::select($sql2);
            $prePreviousYearDcb = DB::select($sql3);

            $data = [
                collect($currentYearDcb)->first(),
                collect($previousYearDcb)->first(),
                collect($prePreviousYearDcb)->first()
            ];
            return responseMsgs(true, "", remove_null($data), "", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $request->deviceId);
        }
    }

    /**
     * | for collecting finantial year's starting date and end date
     * | common functon
     * | @param fyear
        | Serial No : 04.01
        | Working
     */
    public function getFyearDate($fyear)
    {
        list($fromYear, $toYear) = explode("-", $fyear);
        if ($toYear - $fromYear != 1) {
            throw new Exception("Enter Valide Financial Year");
        }
        $fromDate = $fromYear . "-04-01";
        $uptoDate = $toYear . "-03-31";
        return [
            "fromDate" => $fromDate,
            "uptoDate" => $uptoDate
        ];
    }



    /**
     * | Water collection Report for Consumer and Connections
     */
    public function WaterCollection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate"      => "required|date|date_format:Y-m-d",
                "uptoDate"      => "required|date|date_format:Y-m-d",
                "wardId"        => "nullable|digits_between:1,9223372036854775807",
                "userId"        => "nullable|digits_between:1,9223372036854775807",
                "paymentMode"   => "nullable",
                "page"          => "nullable|digits_between:1,9223372036854775807",
                "perPage"       => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $consumerCollection = null;
        $applicationCollection = null;
        $consumerData = 0;
        $consumerTotal = 0;
        $applicationTotal = 0;
        $applicationData = 0;
        $collectionTypes = $request->collectionType;
        $perPage = $request->perPage ?? 5;

        if ($request->user == 'tc') {
            $userId = authUser($request)->id;
            $request->merge(["userId" => $userId]);
        }

        foreach ($collectionTypes as $collectionType) {
            if ($collectionType == 'consumer') {
                $consumerCollection = $this->consumerReport($request);
                $consumerTotal = $consumerCollection->original['data']['totalAmount'];
                $consumerData = $consumerCollection->original['data']['total'];
                $consumerCollection = $consumerCollection->original['data']['data'];
            }

            if ($collectionType == 'connection') {
                $applicationCollection = $this->applicationCollection($request);
                $applicationTotal = $applicationCollection->original['data']['totalAmount'];
                $applicationData = $applicationCollection->original['data']['total'];
                $applicationCollection = $applicationCollection->original['data']['data'];
            }
        }
        $currentPage = $request->page ?? 1;
        $details = collect($consumerCollection)->merge($applicationCollection);

        $a = round($consumerData / $perPage);
        $b = round($applicationData / $perPage);
        $data['current_page'] = $currentPage;
        $data['total'] = $consumerData + $applicationData;
        $data['totalAmt'] = round($consumerTotal + $applicationTotal);
        $data['last_page'] = max($a, $b);
        $data['data'] = $details;

        return responseMsgs(true, "", $data, "", "", "", "post", $request->deviceId);
    }

    /**
     * | Consumer Collection Report 
     */
    public function consumerReport(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate"      => "required|date|date_format:Y-m-d",
                "uptoDate"      => "required|date|date_format:Y-m-d",
                "wardId"        => "nullable|digits_between:1,9223372036854775807",
                "userId"        => "nullable|digits_between:1,9223372036854775807",
                "paymentMode"   => "nullable",
                "page"          => "nullable|digits_between:1,9223372036854775807",
                "perPage"       => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $metaData = collect($request->metaData)->all();
        $request->request->add(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            // DB::enableQueryLog();
            $data = WaterTran::SELECT(
                DB::raw("
                            water_trans.id AS tran_id,
                            water_consumers.id AS ref_consumer_id,
                            ulb_ward_masters.ward_name AS ward_no,
                             'consumer' as type,
                            water_consumers.consumer_no,
                            CONCAT('', water_consumers.holding_no, '') AS holding_no,
                            water_owner_detail.owner_name,
                            water_owner_detail.mobile_no,
                            water_trans.tran_date,
                            water_trans.payment_mode AS transaction_mode,
                            water_trans.amount,users.user_name as emp_name,users.id as user_id,
                            water_trans.tran_no,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name
                "),
            )
                ->JOIN("water_consumers", "water_consumers.id", "water_trans.related_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, 
                                STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                                water_consumer_owners.consumer_id 
                        FROM water_consumer_owners 
                        JOIN water_trans on water_trans.related_id = water_consumer_owners.consumer_id 
                        WHERE water_trans.related_id IS NOT NULL AND water_trans.status in (1, 2) 
                        AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " . ($userId ? " AND water_trans.emp_dtl_id = $userId " : "")
                        . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND water_trans.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY water_consumer_owners.consumer_id
                        ) AS water_owner_detail
                        "),
                    function ($join) {
                        $join->on("water_owner_detail.consumer_id", "=", "water_trans.related_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_consumers.ward_mstr_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->LEFTJOIN("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
                ->WHERE("water_trans.tran_type", "Demand Collection")
                ->WHERENOTNULL("water_trans.related_id")
                ->WHEREIN("water_trans.status", [1, 2])
                ->WHEREBETWEEN("water_trans.tran_date", [$fromDate, $uptoDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $data = $data->where("water_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(water_trans.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("water_trans.ulb_id", $ulbId);
            }
            $paginator = collect();

            $data2 = $data;
            $totalHolding = $data2->count("water_consumers.id");
            $totalAmount = $data2->sum("water_trans.amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $data->paginate($perPage);

            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                "current_page"  => $paginator->currentPage(),
                "last_page"     => $paginator->lastPage(),
                "totalHolding"  => $totalHolding,
                "totalAmount"   => $totalAmount,
                "data"          => $paginator->items(),
                "total"         => $paginator->total(),
                // "numberOfPages" => $numberOfPages
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all());
        }
    }


    /**
     * | Connection Collection Report
     */
    public function connectionCollection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate"      => "required|date|date_format:Y-m-d",
                "uptoDate"      => "required|date|date_format:Y-m-d",
                "wardId"        => "nullable|digits_between:1,9223372036854775807",
                "userId"        => "nullable|digits_between:1,9223372036854775807",
                "paymentMode"   => "nullable",
                "page"          => "nullable|digits_between:1,9223372036854775807",
                "perPage"       => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $request->request->add(["metaData" => ["pr2.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $refTransType = Config::get("waterConstaint.PAYMENT_FOR");

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            DB::enableQueryLog();
            $worflowIds = DB::table('water_applications')->select(DB::raw("trim(concat(workflow_id::text,','),',') workflow_id"))
                ->groupBy("workflow_id")
                ->first();
            $activConnections = WaterTran::select(
                DB::raw("
                            water_trans.id AS tran_id,
                            ulb_ward_masters.ward_name AS ward_no,
                            water_applications.id,
                            water_trans.tran_date,                             
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            water_trans.payment_mode AS transaction_mode,
                            water_trans.amount,
                            users.user_name as emp_name,
                            users.id as user_id,
                            water_trans.tran_no,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name
                "),
            )
                ->JOIN("water_applications", "water_applications.id", "water_trans.related_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, 
                            STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                            water_applicants.application_id 
                        FROM water_applicants 
                        JOIN water_trans on water_trans.related_id = water_applicants.application_id 
                        WHERE water_trans.related_id IS NOT NULL 
                        AND water_trans.status in (1, 2) 
                        AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND water_trans.emp_dtl_id = $userId " : "")
                        . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND water_trans.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY water_applicants.application_id 
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.application_id", "=", "water_trans.related_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_applications.ward_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->LEFTJOIN("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")

                ->JOIN("wf_roleusermaps", "wf_roleusermaps.user_id", "users.id")
                ->JOIN("wf_workflowrolemaps", "wf_workflowrolemaps.wf_role_id", "wf_roleusermaps.wf_role_id")
                ->WHEREIN("wf_workflowrolemaps.workflow_id", explode(",", collect($worflowIds)->implode("workflow_id", ",")))
                ->WHERENULL("water_trans.citizen_id")

                ->WHERENOTNULL("water_trans.related_id")
                ->WHEREIN("water_trans.status", [1, 2])
                ->WHERE("water_trans.tran_type", "<>", $refTransType['1'])
                ->WHEREBETWEEN("water_trans.tran_date", [$fromDate, $uptoDate]);

            $rejectedConnections = WaterTran::select(
                DB::raw("
                            water_trans.id AS tran_id,
                            ulb_ward_masters.ward_name AS ward_no,
                            water_rejection_application_details.id,
                            water_trans.tran_date,
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            water_trans.payment_mode AS transaction_mode,
                            water_trans.amount,
                            users.user_name as emp_name,
                            users.id as user_id,
                            water_trans.tran_no,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name
                "),
            )
                ->JOIN("water_rejection_application_details", "water_rejection_application_details.id", "water_trans.related_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, 
                            STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                            water_rejection_applicants.application_id 
                        FROM water_rejection_applicants 
                        JOIN water_trans on water_trans.related_id = water_rejection_applicants.application_id 
                        WHERE water_trans.related_id IS NOT NULL 
                        AND water_trans.status in (1, 2) 
                        AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND water_trans.emp_dtl_id = $userId " : "")
                        . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND water_trans.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY water_rejection_applicants.application_id 
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.application_id", "=", "water_trans.related_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_rejection_application_details.ward_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->LEFTJOIN("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")

                ->JOIN("wf_roleusermaps", "wf_roleusermaps.user_id", "users.id")
                ->JOIN("wf_workflowrolemaps", "wf_workflowrolemaps.wf_role_id", "wf_roleusermaps.wf_role_id")
                ->WHEREIN("wf_workflowrolemaps.workflow_id", explode(",", collect($worflowIds)->implode("workflow_id", ",")))
                ->WHERENULL("water_trans.citizen_id")

                ->WHERENOTNULL("water_trans.related_id")
                ->WHEREIN("water_trans.status", [1, 2])
                ->WHERE("water_trans.tran_type", "<>", $refTransType['1'])
                ->WHEREBETWEEN("water_trans.tran_date", [$fromDate, $uptoDate]);


            if ($wardId) {
                $activConnections = $activConnections->where("ulb_ward_masters.id", $wardId);
                $rejectedConnections = $rejectedConnections->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $activConnections = $activConnections->where("water_trans.emp_dtl_id", $userId);
                $rejectedConnections = $rejectedConnections->where("water_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $activConnections = $activConnections->where(DB::raw("water_trans.payment_mode"), $paymentMode);
                $rejectedConnections = $rejectedConnections->where(DB::raw("water_trans.payment_mode"), $paymentMode);
            }
            if ($ulbId) {
                $activConnections = $activConnections->where("water_trans.ulb_id", $ulbId);
                $rejectedConnections = $rejectedConnections->where("water_trans.ulb_id", $ulbId);
            }

            $data = $activConnections->union($rejectedConnections);
            // dd($data->ORDERBY("tran_id")->get()->implode("tran_id",","));
            $data2 = $data;
            // $totalApplications = $data2->count("id");
            $totalAmount = $data2->sum("amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $paginator = $data->paginate($perPage);
            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                // "perPage" => $perPage,
                // "page" => $page,
                // "totalApplications" => $totalSaf,
                // "totalAmount" => $totalAmount,
                // "items" => $items,
                // "total" => $total,
                // "numberOfPages" => $numberOfPages

                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * dcb report
      |working
     *
     */
    public function WaterdcbReport(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "wardId" => "nullable|int",
                "zoneId" => "nullable|int"
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $now                        = Carbon::now();
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $wardId = null;
            $userId = null;
            $zoneId = null;
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDate = $refDate['fromDate'];
            $uptoDate = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];
            $dataraw =  "SELECT *,
            (arrear_balance + current_balance) AS total_balance
        FROM (
            SELECT 
                SUM(CASE WHEN demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) AS current_demands,
                SUM(CASE WHEN paid_status = 0 AND demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) AS current_year_balance_amount,
                SUM(CASE WHEN paid_status = 1 AND demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) AS current_year_collection_amount,
                SUM(CASE WHEN paid_status = 1 AND demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END) AS previous_year_collection_amount,
                SUM(CASE WHEN demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END) AS previous_year_demands,
                SUM(CASE WHEN paid_status = 0 AND demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END) AS previous_year_balance_amount,
                (SUM(CASE WHEN demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END) - SUM(CASE WHEN paid_status = 1 AND demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END)) AS arrear_balance,
                (SUM(CASE WHEN demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) - SUM(CASE WHEN paid_status = 1 AND demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END)) AS current_balance,
                (SUM(CASE WHEN demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) + SUM(CASE WHEN water_consumer_demands.STATUS = TRUE AND demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END)) AS total_demand,
                (SUM(CASE WHEN paid_status = 1 AND demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date THEN amount ELSE 0 END) + SUM(CASE WHEN paid_status = 1 AND demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date THEN amount ELSE 0 END)) AS total_collection,
                ulb_ward_masters.ward_name
            FROM water_consumer_demands  
            LEFT JOIN water_second_consumers ON water_consumer_demands.consumer_id = water_second_consumers.id
            LEFT JOIN ulb_ward_masters ON water_second_consumers.ward_mstr_id = ulb_ward_masters.id
            WHERE 
          
                (demand_from >= '$fromDate'::date AND demand_upto <= '$uptoDate'::date AND water_consumer_demands.status = TRUE)
                OR (demand_from >= '$previousFromDate'::date AND demand_upto <= '$previousUptoDate'::date AND water_consumer_demands.STATUS = TRUE)
                
                "
                . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId " : "")
                . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId " : "")
                . "
            "

                . "
                GROUP BY ulb_ward_masters.ward_name
        ) AS subquery
        ";


            $results = DB::connection('pgsql_water')->select($dataraw);
            $resultObject = (object) $results[0];
            return responseMsgs(true, "water demand report", remove_null($resultObject), "", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }


    /**
     * | Get details of water according to applicationNo , consumerNo , etc
     * | maping of water with property  
        | Serial No : 0
        | Under con
     */
    public function getWaterDetailsByParams(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'parmeter' => 'required|',
                'filterBy' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mWaterConsumer = new WaterConsumer();
            $parameter = $request->parmeter;
            $filterBy = $request->filterBy;

            switch ($filterBy) {
                case 'consumerNo':                                      // Static
                    $this->getConsumerRelatedDetails();
                    break;
                case 'applicationNo':                                   // Static
                    $this->getApplicationRelatedDetails();
                    break;
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Get consumer details by consumer id and related property details
        | Serial No :
        | Under Con
     */
    public function getConsumerRelatedDetails() {}

    /**
     * |get transaction lis by year 
       |under wo
     */
    public function getTransactionDetail(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fiYear"    => "nullable|regex:/^\d{4}-\d{4}$/",
                "wardId"    => "nullable|int",
                "zoneId"    => "nullable|int",
                "dateFrom"  => "nullable|date",
                "dateUpto"  => "nullable|date"

            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mWaterTrans            = new WaterTran();
            $dateFrom =  $dateUpto  = Carbon::now()->format('Y-m-d');
            $fiYear = $wardId = $zoneId = null;
            if ($request->dateFrom) {
                $dateFrom = $request->dateFrom;
            }
            if ($request->dateUpto) {
                $dateUpto = $request->dateUpto;
            }
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
                $refDate        = $this->getFyearDate($fiYear);
                $dateFrom       = $refDate['fromDate'];
                $dateUpto       = $refDate['uptoDate'];
            }
            if ($request->wardId) {
                $wardId =  $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId =  $request->zoneId;
            }

            $dataraw = "SELECT
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cash'  THEN water_trans.id ELSE NULL END
         ) AS waterCash,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cheque'  THEN water_trans.id ELSE NULL END
         ) AS waterCheque,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'ONLINE'  THEN water_trans.id ELSE NULL END
         ) AS waterOnline,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'DD'  THEN water_trans.id ELSE NULL END
         ) AS waterDd,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'NEFT'  THEN water_trans.id ELSE NULL END
         ) AS waterNeft,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'RTGS'  THEN water_trans.id ELSE NULL END
         ) AS waterRtgs,
         COUNT(
            CASE WHEN water_trans.status=1 THEN water_trans.id ELSE NULL END
         ) AS NetTotalTransaction,
         
         
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cash' AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcCashCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cheque'AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcChequeCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'ONLINE' AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcOnlineCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'DD' AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcDdCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'NEFT' AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcNeftCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'RTGS' AND  water_trans.user_type = 'TC'  THEN water_trans.id ELSE NULL END
         ) AS TcRtgsCount,
         
         
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cash' AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskCashCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'Cheque'AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskChequeCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'ONLINE' AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskOnlineCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'DD' AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskDdCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'NEFT' AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskNeftCount,
            COUNT(
             CASE WHEN water_trans.payment_mode = 'RTGS' AND  water_trans.user_type = 'JSK'  THEN water_trans.id ELSE NULL END
         ) AS JskRtgsCount,
         
         -- Sum of amount for diff payment mode 
         SUM(CASE WHEN water_trans.payment_mode = 'Cash' THEN COALESCE(amount,0) ELSE 0 END) AS CashTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'Cheque' THEN COALESCE(amount,0) ELSE 0 END) AS ChequeTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'DD' THEN COALESCE(amount,0) ELSE 0 END) AS DdTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'ONLINE' THEN COALESCE(amount,0) ELSE 0 END ) AS OnlineTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'NEFT' THEN COALESCE(amount,0) ELSE 0 END ) AS NeftTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'RTGS' THEN COALESCE(amount,0) ELSE 0 END ) AS RtgsTotalAmount,
         SUM(amount) AS TotalPaymentModeAmount,
         -- Sum of amount of TC for diff payament mode 
         SUM(CASE WHEN water_trans.payment_mode = 'Cash' AND   water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END) AS TcCasTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'Cheque' AND  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END) AS TcChequeTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'DD' AND  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END) AS TcDdTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'ONLINE' AND  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END ) AS TcOnlineTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'NEFT' AND  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END ) AS TcNeftTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'RTGS' AND  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END ) AS TcRtgsTotalAmount,
         SUM(CASE WHEN  water_trans.user_type = 'TC' THEN COALESCE(amount,0) ELSE 0 END) AS tc_total_amount,
           -- Sum of amount of JSK for diff payament mode 
         SUM(CASE WHEN water_trans.payment_mode = 'Cash' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END) AS JskCashTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'Cheque' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END) AS JskChequeTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'DD' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END) AS JskDdTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'ONLINE' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END ) AS JskOnlineTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'NEFT' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END ) AS JskNeftTotalAmount,
         SUM(CASE WHEN water_trans.payment_mode = 'RTGS' AND  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END ) AS JskRtgsTotalAmount,
         SUM(CASE WHEN  water_trans.user_type = 'JSK' THEN COALESCE(amount,0) ELSE 0 END) AS JskTotalAmount
        
        FROM water_trans
        LEFT JOIN water_second_consumers on water_trans.related_id = water_second_consumers.id
        LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
        WHERE water_trans.payment_mode IN ('Cash', 'Cheque', 'DD', 'NEFT', 'RTGS', 'ONLINE')
            AND water_trans.status = 1
            AND water_trans.tran_date BETWEEN '$dateFrom' AND '$dateUpto'
            -- AND water_trans.tran_type = 'Demand Collection'
            "
                . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId " : "")
                . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId " : "")
                . "
         ";
            $results = collect(collect(DB::connection('pgsql_water')->select($dataraw))->first())->map(function ($val) {
                return $val ? $val : 0;
            });
            return responseMsgs(true, "water Dcb report", remove_null($results), "", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }
    /**
     * | water Collection
     */
    public function tCvisitReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::enableQueryLog();
            $data = WaterConsumerDemand::SELECT(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            water_second_consumers.id,
                            'water' as type,
                            water_second_consumers.saf_no,
                            water_second_consumers.user_type,
                            water_second_consumers.property_no,
                            water_second_consumers.address,
                            water_consumer_owners.applicant_name,
                            water_consumer_owners.mobile_no,
                            water_trans.amount,
                            water_trans.tran_date,
                            users.name as name,
                            users.user_name as emp_name,
                            users.id as user_id,
                            users.mobile as tc_mobile,
                            water_trans.tran_no,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name,
                            zone_masters.zone_name,
                            'water_trans.payment_type as paymentStatus'
                            
                "),
            )
                ->leftJOIN("water_second_consumers", "water_second_consumers.id", "water_consumer_demands.related_id")
                ->leftJoin("water_consumer_owners", "water_consumer_owners.consumer_id", "=", "water_second_consumers.id")
                ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')

                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, STRING_AGG(water_consumer_owners.mobile_no::TEXT, ', ') AS mobile_no, water_consumer_owners.consumer_id 
                            FROM water_second_consumers 
                        JOIN water_trans  on water_trans.related_id = water_second_consumers.id
                        JOIN water_consumer_owners on water_consumer_owners.consumer_id = water_second_consumers.id
                        WHERE water_trans.related_id IS NOT NULL AND water_trans.status in (1, 2) 
                     
                        AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND water_trans.emp_dtl_id = $userId " : "")
                        . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND water_trans.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY water_consumer_owners.consumer_id
                        ) AS water_owner_details
                        "),
                    function ($join) {
                        $join->on("water_owner_details.consumer_id", "=", "water_trans.related_id");
                    }

                )
                ->LEFTJOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_second_consumers.ward_mstr_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->LEFTJOIN("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
                ->WHERENOTNULL("water_trans.related_id")
                ->WHEREIN("water_trans.status", [1, 2])
                ->WHERE('tran_type', "=", "Demand Collection")

                ->WHEREBETWEEN("water_trans.tran_date", [$fromDate, $uptoDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $data = $data->where("water_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(water_trans.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("water_trans.ulb_id", $ulbId);
            }
            if ($zoneId) {
                $data = $data->where("water_second_consumers.zone_mstr_id", $zoneId);
            }
            $paginator = collect();

            $data2 = $data;
            $totalConsumers = $data2->count("water_second_consumers.id");
            $totalAmount = $data2->sum("water_trans.amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            if ($request->all) {
                $data = $data->get();
                $mode = collect($data)->unique("transaction_mode")->pluck("transaction_mode");
                $totalFAmount = collect($data)->unique("tran_id")->sum("amount");
                $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
                $footer = $mode->map(function ($val) use ($data) {
                    $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
                    $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
                    return ['mode' => $val, "count" => $count, "amount" => $amount];
                });
                $list = [
                    "data" => $data,

                ];
                $tcName = collect($data)->first()->emp_name ?? "";
                $tcMobile = collect($data)->first()->tc_mobile ?? "";
                if ($request->footer) {
                    $list["tcName"] = $tcName;
                    $list["tcMobile"] = $tcMobile;
                    $list["footer"] = $footer;
                    $list["totalCount"] = $totalFCount;
                    $list["totalAmount"] = $totalFAmount;
                }
                return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            }

            // $paginator = $data->paginate($perPage);

            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                // "current_page" => $paginator->currentPage(),
                // "last_page" => $paginator->lastPage(),
                "totalHolding" => $totalConsumers,
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                // "numberOfPages" => $numberOfPages
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    /**
     * total consumer type report
     Meter/Non-Meter
     */
    public function totalConsumerType(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'zoneId'   => 'nullable',
                'wardId'   => 'nullable',
                'pages'    => 'nullable',
            ]
        );

        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $mWaterSecondConsumer = new WaterSecondConsumer();
            $wardId  = $request->wardId;
            $zoneId    = $request->zone;
            return $getConsumer = $mWaterSecondConsumer->totalConsumerType($wardId, $zoneId)->get();



            // return responseMsgs(true, "total consumer type", remove_null($waterReturnDetails), "", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }

    public function WardList(Request $request)
    {
        $request->request->add(["metaData" => ["tr13.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            if ($request->ulbId) {
                $ulbId  =   $request->ulbId;
            }
            $wardList = UlbWardMaster::select(DB::raw("min(id) as id ,ward_name,ward_name as ward_no"))
                ->WHERE("ulb_id", $ulbId)
                ->GROUPBY("ward_name")
                ->ORDERBY("ward_name")
                ->GET();

            return responseMsgs(true, "", $wardList, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | water Collection
     */
    public function WaterCollectionReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::enableQueryLog();
            $data = waterTran::SELECT(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            water_second_consumers.id,
                            'water' as type,
                            water_second_consumers.saf_no,
                            water_second_consumers.user_type,
                            water_second_consumers.consumer_no,
                            water_trans.id AS tran_id,
                            water_second_consumers.property_no,
                            water_second_consumers.address,
                            water_consumer_owners.applicant_name,
                            water_consumer_owners.mobile_no,
                            water_trans.payment_mode AS transaction_mode,
                            water_trans.amount,
                            water_trans.tran_date,
                            users.name as name,
                            users.user_name as emp_name,
                            users.id as user_id,
                            users.mobile as tc_mobile,
                            water_trans.tran_no,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name,
                            zone_masters.zone_name
                            
                "),
            )
                ->leftJOIN("water_second_consumers", "water_second_consumers.id", "water_trans.related_id")
                ->leftJoin("water_consumer_owners", "water_consumer_owners.consumer_id", "=", "water_second_consumers.id")
                ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')

                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, STRING_AGG(water_consumer_owners.mobile_no::TEXT, ', ') AS mobile_no, water_consumer_owners.consumer_id 
                            FROM water_second_consumers 
                        JOIN water_trans  on water_trans.related_id = water_second_consumers.id
                        JOIN water_consumer_owners on water_consumer_owners.consumer_id = water_second_consumers.id
                        WHERE water_trans.related_id IS NOT NULL AND water_trans.status in (1, 2) 
                     
                        AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND water_trans.emp_dtl_id = $userId " : "")
                        . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND water_trans.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY water_consumer_owners.consumer_id
                        ) AS water_owner_details
                        "),
                    function ($join) {
                        $join->on("water_owner_details.consumer_id", "=", "water_trans.related_id");
                    }

                )
                ->LEFTJOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_second_consumers.ward_mstr_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->LEFTJOIN("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
                ->WHERENOTNULL("water_trans.related_id")
                ->WHEREIN("water_trans.status", [1, 2])
                ->WHERE('tran_type', "=", "Demand Collection")

                ->WHEREBETWEEN("water_trans.tran_date", [$fromDate, $uptoDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $data = $data->where("water_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(water_trans.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("water_trans.ulb_id", $ulbId);
            }
            if ($zoneId) {
                $data = $data->where("water_second_consumers.zone_mstr_id", $zoneId);
            }
            $paginator = collect();

            $data2 = $data;
            $totalConsumers = $data2->count("water_second_consumers.id");
            $totalAmount = $data2->sum("water_trans.amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            if ($request->all) {
                $data = $data->get();
                $mode = collect($data)->unique("transaction_mode")->pluck("transaction_mode");
                $totalFAmount = collect($data)->unique("tran_id")->sum("amount");
                $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
                $footer = $mode->map(function ($val) use ($data) {
                    $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
                    $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
                    return ['mode' => $val, "count" => $count, "amount" => $amount];
                });
                $list = [
                    "data" => $data,

                ];
                $tcName = collect($data)->first()->emp_name ?? "";
                $tcMobile = collect($data)->first()->tc_mobile ?? "";
                if ($request->footer) {
                    $list["tcName"] = $tcName;
                    $list["tcMobile"] = $tcMobile;
                    $list["footer"] = $footer;
                    $list["totalCount"] = $totalFCount;
                    $list["totalAmount"] = $totalFAmount;
                }
                return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            }

            $paginator = $data->paginate($perPage);

            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalHolding" => $totalConsumers,
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                // "numberOfPages" => $numberOfPages
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    # over all tc collection report 
    public function userWiseCollectionSummary(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "userId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? 2;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $wardId = $zoneId = $paymentMode = $userId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $sql = "
                SELECT  water_trans_sub.*,
                    users.id as user_id,
                    users.name,
                    users.mobile,
                    users.photo,
                    users.photo_relative_path
                FROM(
                    SELECT SUM(amount) as total_amount,
                        count(wt.id) as total_tran,
                        count(distinct wt.related_id) as total_water, 
                        wt.emp_dtl_id                   
                    FROM water_trans as wt  
                    JOIN water_second_consumers wsc on wsc.id = wt.related_id                 
                    WHERE wt.status IN (1,2)
                    AND wt.tran_type = 'Demand Collection'
                   
                        AND wt.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? " AND water_second_consumers.zone_mstr_id	 = $zoneId" : "") . "
                        " . ($userId ? " AND wt.emp_dtl_id = $userId" : "") . "
                    GROUP BY wt.emp_dtl_id
                    ORDER BY wt.emp_dtl_id
                ) water_trans_sub
                JOIN users ON users.id = water_trans_sub.emp_dtl_id
            ";
            $sql = "
                    SELECT 
                        users.id as user_id,
                        users.name,
                        users.mobile,
                        users.photo,
                        users.photo_relative_path,
                        COALESCE(water_trans.total_amount, 0) as total_amount,
                        COALESCE(water_trans.total_tran, 0) as total_tran,
                        COALESCE(water_trans.total_property, 0) as total_property
                    FROM users
                    LEFT JOIN (
                        SELECT 
                            SUM(amount) as total_amount,
                            count(water_trans.id) as total_tran,
                            count(distinct water_trans.related_id) as total_property, 
                            water_trans.emp_dtl_id                   
                        FROM water_trans 
                        JOIN water_second_consumers on water_second_consumers.id = water_trans.related_id                   
                        WHERE water_trans.status IN (1,2)
                            AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                            " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                            " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                            " . ($userId ? " AND water_trans.emp_dtl_id = $userId" : "") . "
                            " . ($paymentMode ? " AND upper(water_trans.payment_mode) = upper('$paymentMode')" : "") . "
                        GROUP BY water_trans.emp_dtl_id
                    ) water_trans ON users.id = water_trans.emp_dtl_id
                    WHERE users.user_type = 'TC'
                    AND users.suspended = 'false'
                    ORDER BY users.id
                ";
            $data = DB::connection('pgsql_water')->select($sql . " limit $limit offset $offset");
            // $count = (collect(DB::connection('pgsql_water')->SELECT("SELECT COUNT(*)AS total, SUM(total_amount) AS total_amount FROM ($sql) total"))->first());
            $tran = (collect(DB::connection('pgsql_water')->SELECT("SELECT COUNT(*)AS total, SUM(total_tran) AS total_tran FROM ($sql) total"))->first());
            $count = collect($this->_DB->SELECT("
            SELECT COUNT(*) AS total, 
                SUM(total_amount) AS total_amount,
                SUM(total_tran) as total_tran
            FROM ($sql) total
                    "))->first();
            $total = ($count)->total ?? 0;
            $sum = ($count)->total_amount ?? 0;
            $lastPage = ceil($total / $perPage);
            $total = ($tran)->total ?? 0;
            $tran_sum = ($tran)->total_tran ?? 0;
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "total_sum" => $sum,
                "per_page" => $perPage,
                "last_page" => $lastPage,
                "total_tran_sum" => $tran_sum
            ];
            return responseMsgs(true, "", $list, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }
    /**
     * water ward wise dcb
     */
    public function WaterWardWiseDCB(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                // "page" => "nullable|digits_between:1,9223372036854775807",
                // "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr8.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $zone           = null;
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $now                        = Carbon::now();
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDate = $refDate['fromDate'];
            $uptoDate = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId || $request->zone) {
                $zoneId = $request->zoneId ?? $request->zone;
            }
            if (in_array($zoneId, ['gov', 'SUS'])) {
                $zoneId = null;
                $zone = $request->zoneId;
            }
            $from = "
            FROM ulb_ward_masters 
            LEFT JOIN(
                SELECT water_second_consumers.ward_mstr_id, 
                COUNT(DISTINCT water_consumer_demands.consumer_id) AS ref_consumer_count,
                COUNT(DISTINCT water_consumer_demands.consumer_id) AS consumer_count_total,       
                    COUNT(DISTINCT (CASE WHEN water_consumer_demands.demand_from >= '$fromDate' AND water_consumer_demands.demand_upto <= '$uptoDate'  THEN water_consumer_demands.consumer_id               
                                    END)                        
                        ) as current_demand_hh,    
                    SUM(              
                        CASE WHEN water_consumer_demands.demand_from >= '$fromDate' AND water_consumer_demands.demand_upto <= '$uptoDate' THEN water_consumer_demands.balance_amount          
                        ELSE 0                                   
                        END                         
                    ) AS current_demand,       
                    COUNT(DISTINCT ( CASE WHEN water_consumer_demands.demand_upto <= '$previousUptoDate'  THEN water_consumer_demands.consumer_id    
                                    END)                            
                        ) as arrear_demand_hh,                       
                    SUM(water_consumer_demands.balance_amount ) AS arrear_demand,     
                    SUM(amount) AS total_demand,
                    COUNT(DISTINCT (CASE WHEN  water_consumer_demands.demand_from >= '$fromDate' AND water_consumer_demands.demand_upto <= '$uptoDate'   AND water_consumer_demands.paid_status =1 THEN water_consumer_demands.consumer_id               
                                        END)                        
                            ) as current_collection_hh,  
                    SUM(              
                            CASE WHEN water_consumer_demands.demand_from >= '$fromDate' AND water_consumer_demands.demand_upto <= '$uptoDate'  AND water_consumer_demands.paid_status =1 THEN water_consumer_demands.balance_amount          
                            ELSE 0                                   
                            END                        
                        ) AS current_collection,
                    COUNT(DISTINCT ( CASE WHEN water_consumer_demands.demand_upto <= '$previousUptoDate' AND water_consumer_demands.paid_status =1 THEN water_consumer_demands.consumer_id
                                        END)                            
                            ) as arrear_collection_hh, 
                    SUM(CASE WHEN water_consumer_demands.demand_upto <= '$previousUptoDate'  AND water_consumer_demands.paid_status =1  THEN water_consumer_demands.balance_amount ELSE 0 END ) AS arrear_collection,
                    SUM(CASE WHEN water_consumer_demands.paid_status =1  then water_consumer_demands.balance_amount ELSE 0 END) AS total_collection,
                    COUNT(DISTINCT(CASE WHEN water_consumer_demands.paid_status =1  then water_second_consumers.id end)) AS collection_from_no_of_hh 
                FROM water_consumer_demands                    
                JOIN water_second_consumers ON water_second_consumers.id = water_consumer_demands.consumer_id
                WHERE water_consumer_demands.status =true
                    AND water_consumer_demands.ulb_id =$ulbId   
                    " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                    -- AND water_consumer_demands.demand_upto <= '$uptoDate'
                GROUP BY water_second_consumers.ward_mstr_id
            )demands ON demands.ward_mstr_id = ulb_ward_masters.id   
            left join(
                SELECT water_second_consumers.ward_mstr_id, SUM(0)AS balance
                FROM water_second_consumers
                where water_second_consumers.status = 1 
                    AND water_second_consumers.ulb_id =$ulbId
                " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                GROUP BY water_second_consumers.ward_mstr_id
            ) AS arrear  on arrear.ward_mstr_id = ulb_ward_masters.id                            
            WHERE  ulb_ward_masters.ulb_id = $ulbId  
                " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                " . ($zoneId ? " AND ulb_ward_masters.zone = $zoneId" : "") . "
                AND ulb_ward_masters.status = 1
            GROUP BY ulb_ward_masters.ward_name,
            demands.ref_consumer_count,
            demands.consumer_count_total         
        ";

            $select = "SELECT ulb_ward_masters.ward_name AS ward_no,ulb_ward_masters.ward_name,ref_consumer_count,consumer_count_total,
                            SUM(COALESCE(demands.current_demand_hh, 0::numeric)) AS current_demand_hh,   
                            SUM(COALESCE(demands.arrear_demand_hh, 0::numeric)) AS arrear_demand_hh,      
                            SUM(COALESCE(demands.current_collection_hh, 0::numeric)) AS current_collection_hh,  
                            SUM(COALESCE(demands.arrear_collection_hh, 0::numeric)) AS arrear_collection_hh,      
                            SUM(COALESCE(demands.collection_from_no_of_hh, 0::numeric)) AS collection_from_hh,   
                            round(
                                SUM(
                                    (
                                            COALESCE(demands.arrear_collection_hh, 0::numeric) 
                                            / (case when COALESCE(demands.arrear_demand_hh, 0::numeric) > 0  then demands.arrear_demand_hh else 1 end)
                                    )
                                    *100
                                )
                            ) AS arrear_hh_eff,                            
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.current_collection_hh, 0::numeric) 
                                        / (case when COALESCE(demands.current_demand_hh, 0::numeric) > 0 then demands.current_demand_hh else 1 end)
                                    )
                                    *100
                                )
                            ) AS current_hh_eff,                            
                            round(
                                SUM(
                                    COALESCE(                                
                                        COALESCE(demands.current_demand_hh, 0::numeric)  
                                        +COALESCE(demands.arrear_demand_hh, 0::numeric)
                                        - COALESCE(demands.collection_from_no_of_hh, 0::numeric), 0::numeric     
                                    )
                                )
                            ) AS balance_hh,                                                   
                            round(
                                SUM(
                                    COALESCE(                                
                                        COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)   
                                    )
                                )
                            ) AS arrear_demand,    
                            round(SUM(COALESCE(demands.current_demand, 0::numeric))) AS current_demand,   
                            round(SUM(COALESCE(demands.arrear_collection, 0::numeric))) AS arrear_collection,   
                            round(SUM(COALESCE(demands.current_collection, 0::numeric))) AS current_collection, 
                            round(
                                SUM(
                                    (                                    
                                        COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)                              
                                        - COALESCE(demands.arrear_collection, 0::numeric)           
                                    )
                                )
                            )AS old_due,                          
                            round(SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(demands.current_collection, 0::numeric)))) AS current_due,    
                            round(SUM((COALESCE(demands.current_demand_hh, 0::numeric) - COALESCE(demands.current_collection_hh, 0::numeric)))) AS current_balance_hh, 
                            round(SUM((COALESCE(demands.ref_consumer_count, 0::numeric) - COALESCE(demands.arrear_collection_hh, 0::numeric)))) AS arrear_balance_hh,   
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.arrear_collection ::numeric , 0::numeric)
                                        / (case when (COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)) > 0 then demands.arrear_demand else 1 end)
                                        
                                    )
                                    *100
                                )
                            ) AS arrear_eff,                            
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.current_collection, 0::numeric)
                                            / (case when COALESCE(demands.current_demand, 0::numeric) > 0  then demands.current_demand else 1 end)
                                        
                                    )
                                    *100
                                )
                            ) AS current_eff,
                            round(
                                SUM(
                                    (                                
                                        COALESCE(                                   
                                            COALESCE(demands.current_demand, 0::numeric)         
                                            + (                                       
                                                COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)  
                                            ), 0::numeric                                
                                        )                                 
                                        - COALESCE(                              
                                            COALESCE(demands.current_collection, 0::numeric)   
                                            + COALESCE(demands.arrear_collection, 0::numeric), 0::numeric     
                                        )                            
                                    )
                                )
                            ) AS outstanding                                  
            ";
            $dcb = DB::connection('pgsql_water')->select($select . $from);

            $data['total_consumer_count'] = round(collect($dcb)->sum('ref_consumer_count'), 0);
            $data['total_arrear_demand'] = round(collect($dcb)->sum('arrear_demand'), 0);
            $data['total_current_demand'] = round(collect($dcb)->sum('current_demand'), 0);
            $data['total_arrear_collection'] = round(collect($dcb)->sum('arrear_collection'), 0);
            $data['total_current_collection'] = round(collect($dcb)->sum('current_collection'), 0);
            $data['total_old_due'] = round(collect($dcb)->sum('old_due'), 0);
            $data['total_current_due'] = round(collect($dcb)->sum('current_due'), 0);
            $data['total_arrear_demand_hh'] = round(collect($dcb)->sum('arrear_demand_hh'), 0);
            $data['total_current_demand_hh'] = round(collect($dcb)->sum('current_demand_hh'), 0);
            $data['total_arrear_collection_hh'] = round(collect($dcb)->sum('arrear_collection_hh'), 0);
            $data['total_current_collection_hh'] = round(collect($dcb)->sum('current_collection_hh'), 0);
            $data['total_arrear_balance_hh'] = round(collect($dcb)->sum('arrear_balance_hh'));
            $data['total_current_balance_hh'] = round(collect($dcb)->sum('current_balance_hh'));
            // $data['total_current_eff'] = round(($data['total_current_collection_hh'] / $data['total_current_demand']) * 100);
            // $data['total_arrear_hh_eff'] = round(($data['total_arrear_collection_hh'] /  $data['total_arrear_demand_hh']) * 100);
            // $data['total_current_hh_eff'] = round(($data['total_current_collection_hh']) / ($data['total_current_demand_hh']) * 100);
            // $data['total_arrear_eff'] = round(($data['total_arrear_collection']) / ($data['total_arrear_demand']) * 100);
            $refCollection = $data['total_arrear_collection'] + $data['total_current_collection'];
            $refDemand = $data['total_arrear_demand'] + $data['total_current_demand'];
            $data['total_eff'] = round((($refCollection) / ($refDemand == 0 ? 1 : $refDemand)) * 100);
            $data['dcb'] = collect($dcb)->sortBy(function ($item) {
                // Extract the numeric part from the "ward_name"
                preg_match('/\d+/', $item->ward_name, $matches);
                return (int) ($matches[0] ?? "");
            })->values();

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    /**
     * | water demands
     */
    public function WaterDemandsReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {
            $metertype      = null;
            $propertyType   = $request->propertyType;
            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            else
                $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->metertype == 1) {
                $metertype = 'Meter';
            }
            if ($request->metertype == 2) {
                $metertype = 'Fixed';
            }
            // if ($request->propertyType == 1){
            //     $propertyType = 'Resedential';

            // }
            // if ($request->propertyType == 2){
            //     $propertyType = 'Commercial';

            // }


            // DB::connection('pgsql_water')->enableQueryLog();

            $rawData = ("SELECT 
            water_consumer_demands.*,
            ulb_ward_masters.ward_name AS ward_no,
            water_second_consumers.id,
            'water' as type,
            water_second_consumers.consumer_no,
            water_second_consumers.user_type,
            water_second_consumers.property_no,
            water_second_consumers.address,
            water_consumer_owners.applicant_name,
            water_consumer_owners.guardian_name,
            water_consumer_owners.mobile_no,
            water_second_consumers.ward_mstr_id,
            zone_masters.zone_name,
            water_property_type_mstrs.property_type
        FROM (
            SELECT 
                COUNT(water_consumer_demands.id)as demand_count,
                SUM(due_balance_amount) as sum_balance_amount,
                water_consumer_demands.consumer_id,
                water_consumer_demands.connection_type,
                water_consumer_demands.status,
                min(water_consumer_demands.demand_from) as demand_from ,
                max(water_consumer_demands.demand_upto) as demand_upto
            FROM water_consumer_demands
            WHERE  
                 water_consumer_demands.status = TRUE
                AND water_consumer_demands.consumer_id IS NOT NULL
                AND water_consumer_demands.paid_status= 0
            GROUP BY water_consumer_demands.consumer_id, 
                             water_consumer_demands.connection_type,
                             water_consumer_demands.status
        ) water_consumer_demands
        JOIN water_second_consumers ON water_second_consumers.id = water_consumer_demands.consumer_id
        LEFT JOIN water_consumer_owners ON water_consumer_owners.consumer_id = water_second_consumers.id
        LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
        LEFT JOIN water_property_type_mstrs ON water_property_type_mstrs.id =water_second_consumers.property_type_id
        JOIN (
            SELECT 
                STRING_AGG(applicant_name, ', ') AS owner_name, 
                STRING_AGG(water_consumer_owners.mobile_no::TEXT, ', ') AS mobile_no, 
                water_consumer_owners.consumer_id 
            FROM water_second_consumers 
            JOIN water_consumer_demands ON water_consumer_demands.consumer_id = water_second_consumers.id
            JOIN water_consumer_owners ON water_consumer_owners.consumer_id = water_second_consumers.id
            GROUP BY water_consumer_owners.consumer_id
        ) owners ON owners.consumer_id = water_second_consumers.id
        WHERE water_consumer_demands.status = true
        ");

            // return ["details" => $data->get()];
            if ($wardId) {
                $rawData = $rawData . "and ulb_ward_masters.id = $wardId";
            }
            if ($zoneId) {
                $rawData = $rawData . " and water_second_consumers.zone_mstr_id = $zoneId";
            }
            if ($metertype) {
                $rawData = $rawData . "and water_consumer_demands.connection_type = '$metertype'";
            }
            if ($propertyType) {
                $rawData = $rawData . "and water_second_consumers.property_type_id = '$propertyType'";
            }

            $data = DB::connection('pgsql_water')->select(DB::raw($rawData . " OFFSET 0
                    LIMIT $perPage"));

            $count = (collect(DB::connection('pgsql_water')->SELECT("SELECT COUNT(*) AS total
                                FROM ($rawData) total"))->first());                                           // consumer count by searching format 
            $amount =  (collect(DB::connection('pgsql_water')->SELECT("SELECT  SUM(sum_balance_amount) AS total  
            FROM ($rawData) total"))->first());                                                              // consumer amount  

            $total = ($count)->total ?? 0;
            $totalAmount = ($amount)->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "totalAmount" => $totalAmount,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);


            // return ["kjsfd" => $data];
            $paginator = collect();

            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $data2 = DB::connection('pgsql_water')->select(DB::raw($rawData));
            $totalConsumers = collect($data2)->unique("id")->count("water_second_consumers.id");
            $totalAmount = collect($data2)->sum("demandamount");

            // if ($request->all) {
            //     $data = $data->get();
            //     $mode = collect($data)->unique("consumer_id")->pluck("transaction_mode");
            //     $totalFAmount = collect($data)->unique("consumer_id")->sum("amount");
            //     $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
            //     $footer = $mode->map(function ($val) use ($data) {
            //         $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
            //         $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
            //         return ['mode' => $val, "count" => $count, "amount" => $amount];
            //     });
            //     $list = [
            //         "data" => $data,

            //     ];
            //     $tcName = collect($data)->first()->emp_name ?? "";
            //     $tcMobile = collect($data)->first()->tc_mobile ?? "";
            //     if ($request->footer) {
            //         $list["tcName"] = $tcName;
            //         $list["tcMobile"] = $tcMobile;
            //         $list["footer"] = $footer;
            //         $list["totalCount"] = $totalFCount;
            //         $list["totalAmount"] = $totalFAmount;
            //     }
            //     return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            // }



            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalConsumers" => $totalConsumers,
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            // $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    /**
     * | water Collection
     */
    public function WaterCollectionConsumerReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            else
                $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::enableQueryLog();
            $data = waterTran::SELECT(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            water_second_consumers.id,
                            'water' as type,
                            water_second_consumers.saf_no,
                            water_second_consumers.user_type,
                            water_second_consumers.property_no,
                            water_second_consumers.consumer_no,
                            water_second_consumers.address,
                            water_consumer_owners.applicant_name,
                            water_consumer_owners.mobile_no,
                            water_consumer_owners.guardian_name,
                            users.name as name,
                            users.user_name as emp_name,
                            users.id as user_id,
                            users.mobile as tc_mobile,
                            water_cheque_dtls.cheque_no,
                            water_cheque_dtls.bank_name,
                            water_cheque_dtls.branch_name,
                            zone_masters.zone_name,
                            water_consumer_demands.connection_type,
                            water_second_consumers.meter_no,
                            water_consumer_initial_meters.initial_reading,
                            water_consumer_demands.demand_upto,
                            water_consumer_demands.demand_from,
                            water_consumer_demands.amount

                            
                "),
            )
                ->leftJOIN("water_second_consumers", "water_second_consumers.id", "water_trans.related_id")
                ->leftJoin("water_consumer_owners", "water_consumer_owners.consumer_id", "=", "water_second_consumers.id")
                ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
                ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', 'water_second_consumers.id')
                ->join('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', 'water_second_consumers.id')
                ->orderByDesc('water_consumer_initial_meters.id')

                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(applicant_name, ', ') AS owner_name, STRING_AGG(water_consumer_owners.mobile_no::TEXT, ', ') AS mobile_no, water_consumer_owners.consumer_id 
                            FROM water_second_consumers 
                        JOIN water_consumer_demands  on water_consumer_demands.consumer_id = water_second_consumers.id
                        JOIN water_consumer_owners on water_consumer_owners.consumer_id = water_second_consumers.id
                        WHERE water_consumer_demands.consumer_id IS NOT NULL AND water_consume_demands.status in (1, 2) 
                     
                        AND water_consumer_demands.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        GROUP BY water_consumer_owners.consumer_id
                        ) AS water_owner_details
                        "),
                    function ($join) {
                        $join->on("water_owner_details.consumer_id", "=", "water_trans.related_id");
                    }

                )
                ->LEFTJOIN("ulb_ward_masters", "ulb_ward_masters.id", "water_second_consumers.ward_mstr_id")
                ->LEFTJOIN("users", "users.id", "water_trans.emp_dtl_id")
                ->where('water_consumer_demands.demand_from', $fromDate)
                ->where('water_consumer_demands.demand_upto', $uptoDate);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $data = $data->where("water_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(water_trans.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("water_trans.ulb_id", $ulbId);
            }
            if ($zoneId) {
                $data = $data->where("water_second_consumers.zone_mstr_id", $zoneId);
            }
            $paginator = collect();

            $data2 = $data;
            $totalConsumers = $data2->count("water_consumer_demands.id");
            $totalAmount = $data2->sum("water_consumer_demands.amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            // if ($request->all) {
            //     $data = $data->get();
            //     $mode = collect($data)->unique("transaction_mode")->pluck("transaction_mode");
            //     $totalFAmount = collect($data)->unique("tran_id")->sum("amount");
            //     $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
            //     $footer = $mode->map(function ($val) use ($data) {
            //         $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
            //         $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
            //         return ['mode' => $val, "count" => $count, "amount" => $amount];
            //     });
            //     $list = [
            //         "data" => $data,

            //     ];
            //     $tcName = collect($data)->first()->emp_name ?? "";
            //     $tcMobile = collect($data)->first()->tc_mobile ?? "";
            //     if ($request->footer) {
            //         $list["tcName"] = $tcName;
            //         $list["tcMobile"] = $tcMobile;
            //         $list["footer"] = $footer;
            //         $list["totalCount"] = $totalFCount;
            //         $list["totalAmount"] = $totalFAmount;
            //     }
            //     return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            // }

            $paginator = $data->paginate($perPage);

            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalHolding" => $totalConsumers,
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                // "numberOfPages" => $numberOfPages
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    /**
     * | Ward wise demand report
     */
    public function wardWiseConsumerReport(Request $request)
    {
        $mconsumerDemand = new waterConsumerDemand();
        $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
        $ulbId = null;
        $wardId = null;
        $perPage = 5;
        if ($request->fromDate) {
            $fromDate = $request->fromDate;
        }
        if ($request->uptoDate) {
            $uptoDate = $request->uptoDate;
        }
        if ($request->wardId) {
            $wardId = $request->wardId;
        }

        if ($request->ulbId) {
            $ulbId = $request->ulbId;
        }
        if ($request->zoneId) {
            $zoneId = $request->zoneId;
        }
        if ($request->perPage) {
            $perPage = $request->perPage ?? 1;
        }
        $data = $mconsumerDemand->wardWiseConsumer($fromDate, $uptoDate, $wardId, $ulbId, $perPage)->paginate($perPage);;
        if (!$data) {
            throw new Exception('no demand found!');
        }

        $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
        return responseMsgs(true, "Ward Wise Demand Data!", remove_null($data), 'pr6.1', '1.1', $queryRunTime, 'Post', '');
    }

    /**
     * water billingSummary
     */
    public function billingSummary(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                // "page" => "nullable|digits_between:1,9223372036854775807",
                // "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr8.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId || $request->zone) {
                $zoneId = $request->zoneId ?? $request->zone;
            }
            $rawData = ("SELECT 
            subquery.total_unpaid_demand_of_meter,
            subquery.total_unpaid_demand_of_fixed,
            subquery.unpaid_meter_count,
            subquery.unpaid_fixed_count,
            subquery.paid_meter_amount,
            subquery.paid_fixed_amount,
            subquery.meter_paid_bill_count,
            subquery.fixed_paid_bill_count,
            subquery.unpaid_meter_count + subquery.unpaid_fixed_count AS total_unpaid_billing_count,
            subquery.total_unpaid_demand_of_meter+subquery.total_unpaid_demand_of_fixed AS total_unpaid_amount,
            subquery.meter_paid_bill_count + subquery.fixed_paid_bill_count as total_paid_bills_count,
            subquery.unpaid_meter_count+subquery.unpaid_fixed_count as total_unpaid_bill_count,
            subquery.ward_name,
            subquery.unpaid_residential_consumer_count,
            subquery.unpaid_commercial_consumer_count,
            subquery.unpaid_residential_amount,
            subquery.unpaid_commercial_amount
        FROM (
            SELECT 
                SUM(CASE WHEN water_consumer_demands.connection_type='Meter' AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.balance_amount ELSE 0 END) AS total_unpaid_demand_of_meter,
                SUM(CASE WHEN water_consumer_demands.connection_type='Fixed' AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.balance_amount ELSE 0 END) AS total_unpaid_demand_of_fixed, 
                SUM(CASE WHEN water_consumer_demands.connection_type='Meter' AND water_consumer_demands.paid_status=1 THEN water_consumer_demands.balance_amount ELSE 0 END) AS paid_meter_amount, 
                SUM(CASE WHEN water_consumer_demands.connection_type='Fixed' AND water_consumer_demands.paid_status=1 THEN water_consumer_demands.balance_amount ELSE 0 END) AS paid_fixed_amount, 
                SUM (CASE WHEN water_second_consumers.property_type_id=1  AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.balance_amount ELSE 0 END )AS unpaid_residential_amount,
                SUM (CASE WHEN water_second_consumers.property_type_id=2 AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.balance_amount ELSE 0 END ) AS unpaid_commercial_amount,
                COUNT(DISTINCT CASE WHEN water_consumer_demands.connection_type='Meter' AND water_consumer_demands.paid_status=1 THEN water_consumer_demands.consumer_id ELSE 0 END) AS meter_paid_bill_count, 
                COUNT(DISTINCT CASE WHEN water_consumer_demands.connection_type='Fixed' AND water_consumer_demands.paid_status=1 THEN water_consumer_demands.consumer_id ELSE 0 END) AS fixed_paid_bill_count,
                COUNT(DISTINCT CASE WHEN water_consumer_demands.connection_type='Meter' AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.consumer_id ELSE 0 END) AS unpaid_meter_count,
                COUNT(DISTINCT CASE WHEN water_consumer_demands.connection_type='Fixed' AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.consumer_id ELSE 0 END) AS unpaid_fixed_count,
					 COUNT (DISTINCT CASE WHEN water_second_consumers.property_type_id=1 AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.consumer_id ELSE 0 END ) AS unpaid_residential_consumer_count,
					 COUNT(DISTINCT CASE WHEN water_second_consumers.property_type_id=2 AND water_consumer_demands.paid_status=0 THEN water_consumer_demands.balance_amount ELSE 0 END ) AS unpaid_commercial_consumer_count,
                ulb_ward_masters.ward_name
            FROM
                water_second_consumers
            JOIN water_consumer_demands ON water_consumer_demands.consumer_id = water_second_consumers.id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
            JOIN water_consumer_meters ON water_second_consumers.id = water_consumer_meters.consumer_id
            JOIN water_property_type_mstrs ON water_property_type_mstrs.id=water_second_consumers.property_type_id
            WHERE 
               water_consumer_demands.status = TRUE
                " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                " . ($userId ? " AND wt.emp_dtl_id = $userId" : "") . "
            GROUP BY
                ulb_ward_masters.ward_name
        ) AS subquery");
            $billing = DB::connection('pgsql_water')->select($rawData);
            $resultObject = (object) $billing[0];

            return responseMsgs(true, "", $resultObject, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * bulk demand bill 
     */

    public function waterBulkdemand(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            //   return $request;
            $docUrl                     = $this->_docUrl;
            $metertype      = null;
            // $refUser        = authUser($request);
            // $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;
            $NowDate                    = Carbon::now()->format('Y-m-d');
            $bilDueDate                 = Carbon::now()->addDays(15)->format('Y-m-d');
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            // else
            //     $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->metertype == 1) {
                $metertype = 'Meter';
            }
            if ($request->metertype == 2) {
                $metertype = 'Fixed';
            }
            //    return  $offset;
            DB::enableQueryLog();
            $rawData = ("SELECT 
                    distinct(water_consumer_demands.consumer_id),
                    water_consumer_demands.generation_date,
                    ulb_ward_masters.ward_name AS ward_no,
                    water_second_consumers.id,
                    'water' AS type,
                    water_second_consumers.consumer_no as consumerno,
                    water_second_consumers.user_type as usertype,
                    water_second_consumers.property_no as propertyno,
                    water_second_consumers.address,
                    water_second_consumers.tab_size,
                    water_second_consumers.zone,
                    water_consumer_owners.applicant_name as applicantName,
                    water_consumer_owners.guardian_name,
                    water_consumer_owners.mobile_no,
                    water_second_consumers.category,
                    water_second_consumers.folio_no as foliono,
                    water_second_consumers.ward_mstr_id,
                    water_meter_reading_docs.relative_path,
                    water_meter_reading_docs.file_name,
                    MAX(water_consumer_initial_meters.initial_reading) AS finalreading,
                    MIN(water_consumer_initial_meters.initial_reading) AS initialreading,
                    MAX(water_consumer_initial_meters.initial_reading) - MIN(water_consumer_initial_meters.initial_reading) AS unitconsumed,
                    CONCAT('$docUrl', '/', water_meter_reading_docs.relative_path, '/', water_meter_reading_docs.file_name) AS meterImg,
                    CONCAT('$NowDate') AS billdate,
                    CONCAT('$bilDueDate') AS bildueDate, 
                    zone_masters.zone_name, 
                    subquery.generate_amount,
                    subquery.arrear_demands,
                    subquery.current_demands,
                    subquery.demand_from,
                    subquery.demand_upto,
                    subquery.total_amount,
                    subquery.arrear_demand_date,
                    subquery.current_demand_date,
                    users.user_name
                   
                FROM (
                    SELECT  
                        wd.generation_date,
                        wd.emp_details_id,
                        wd.consumer_id,
                        wd.connection_type,
                        wd.status,
                        wd.demand_no,
                        SUM(wd.due_balance_amount) AS sum_amount,
                        MAX(wd.id) AS demand_id     
                    FROM water_consumer_demands wd
                    WHERE  
                        wd.status = TRUE
                        AND wd.consumer_id IS NOT NULL
                        AND wd.is_full_paid = false
                    GROUP BY 
                        wd.consumer_id, 
                        wd.connection_type,
                        wd.status, 
                        wd.demand_no,
                        wd.emp_details_id,
                        wd.generation_date
                   
                ) water_consumer_demands
                JOIN water_second_consumers ON water_second_consumers.id = water_consumer_demands.consumer_id
                LEFT JOIN water_consumer_owners ON water_consumer_owners.consumer_id = water_second_consumers.id
                LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
                LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
                LEFT JOIN water_meter_reading_docs ON water_meter_reading_docs.demand_id = water_consumer_demands.demand_id
                JOIN water_consumer_initial_meters ON water_consumer_initial_meters.consumer_id = water_second_consumers.id
                LEFT JOIN users on users.id = water_consumer_demands.emp_details_id
                LEFT JOIN (
                SELECT 
                    distinct consumer_id, MIN(demand_from) AS demand_from,MAX(demand_upto) AS demand_upto,
                    sum(water_consumer_demands.due_balance_amount) as total_amount,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.arrear_demand ELSE 0 END) AS arrear_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.current_demand ELSE 0 END) AS current_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NOT NULL THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS generate_amount,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.arrear_demand_date else null end ) as arrear_demand_date,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.current_demand_date else null end ) as current_demand_date
                    FROM water_consumer_demands
                    GROUP BY consumer_id
                    ORDER BY water_consumer_demands.consumer_id 
                      
            ) AS subquery ON subquery.consumer_id = water_consumer_demands.consumer_id
                WHERE 
                    water_second_consumers.zone_mstr_id = $zoneId
                    " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                    " . ($metertype ? " AND water_consumer_demands.connection_type='$metertype'" : "") . "
                GROUP BY 
                    water_consumer_demands.generation_date,
                    water_consumer_demands.consumer_id,
                    water_meter_reading_docs.file_name,
                    water_meter_reading_docs.relative_path,
                    water_consumer_demands.demand_id,
                    water_consumer_demands.consumer_id,
                    water_consumer_demands.connection_type,
                    water_consumer_demands.status,
                    water_consumer_demands.sum_amount,
                    water_consumer_demands.demand_no,
                    water_consumer_demands.emp_details_id,
                    ulb_ward_masters.ward_name,
                    water_second_consumers.id,
                    water_consumer_owners.applicant_name,
                    water_consumer_owners.guardian_name,
                    water_consumer_owners.mobile_no,
                    water_second_consumers.category,
                    water_second_consumers.folio_no,
                    water_second_consumers.ward_mstr_id,
                    zone_masters.zone_name,
                    subquery.generate_amount,
                    subquery.arrear_demands,
                    subquery.current_demands,
                    subquery.demand_from,
                    subquery.demand_upto,
                    subquery.total_amount ,
                    subquery.arrear_demand_date,
                    subquery.current_demand_date, 
                    users.user_name
                    ORDER BY water_consumer_demands.consumer_id   

                   
            ");
            // return $offset;
            //   return  DB::connection('pgsql_water')->select($rawData);

            // dd(($rawData));
            $data = DB::connection('pgsql_water')->select(DB::raw($rawData));
            // $totalQuery = "SELECT COUNT(*) AS total FROM ($rawData) AS subquery_total";
            // $total = (collect(DB::SELECT($totalQuery))->first())->total ?? 0;
            // $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                // "total" => $total,
                "per_page" => $perPage,
                // "last_page" => $lastPage - 1
            ];
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function waterBulkdemandV2(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $NowDate    = Carbon::now()->format('Y-m-d');
            $bilDueDate = Carbon::now()->addDays(15)->format('Y-m-d');
            $fromDate    = $uptoDate = Carbon::now()->format("Y-m-d");
            $docUrl      = $this->_docUrl;
            $metertype   = $wardId = $userId = $zoneId = $paymentMode = null;
            $fiYear      = $request->fiYear;
            $quater      = $request->quater;

            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;
            if ($request->quater != null) {
                $refDate = json_decode(getMonthsByQuarter($fiYear, $quater), true);  // Decode the JSON to an array
                $fromDate = $refDate['start_date'];  // First date of the quarter
                $uptoDate = $refDate['end_date'];  // Last date of the quarter

            }

            // if ($request->fromDate) {
            //     $fromDate = $request->fromDate;
            // }
            // if ($request->uptoDate) {
            //     $uptoDate = $request->uptoDate;
            // }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;

            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->metertype == 1) {
                $metertype = '1,2';
            }
            if ($request->metertype == 2) {
                $metertype = '3';
            }
            // sum(
            //     wd.due_balance_amount
            // ) as total_amount, 

            $with = "
                WITH demands AS (
                    SELECT wd.consumer_id, count(wd.id)  , string_agg(wd.connection_type,', ') as demand_type,
                        SUM(wd.due_balance_amount) AS sum_amount, 
                        max(wd.generation_date) as generation_date,
                        MAX(wd.id) AS demand_id ,
                        max(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.final_reading end) as upto_reading,
                        min(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.initial_reading end) as from_reading,
                        MIN(case when wd.is_full_paid=false then wd.demand_from  end ) AS demand_from, 
                        MAX(case when wd.is_full_paid=false then wd.demand_upto  end ) AS demand_upto, 
                    
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NULL THEN wd.arrear_demand ELSE 0 END
                        ) AS arrear_demands, 
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NULL THEN wd.current_demand ELSE 0 END
                        ) AS current_demands, 
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NOT NULL THEN wd.due_balance_amount ELSE 0 END
                        ) AS generate_amount, 
                        max(
                            case when wd.consumer_tax_id is null then wd.arrear_demand_date else null end
                        ) as arrear_demand_date, 
                        max(
                            case when wd.consumer_tax_id is null then wd.current_demand_date else null end
                        ) as current_demand_date 
                    FROM water_consumer_demands wd 
                    left join water_consumer_taxes on water_consumer_taxes.id=  wd.consumer_tax_id
                    WHERE 

                    wd.status = TRUE 
                    AND wd.consumer_id IS NOT NULL 
                    AND wd.due_balance_amount>0 
                    GROUP BY 
                    wd.consumer_id 
                ),
                final_demands AS(
                    select demands.*,
                        water_consumer_demands.emp_details_id, 
                        water_consumer_demands.status, 
                        water_consumer_demands.demand_no ,
                        water_consumer_demands.connection_type ,
                        CASE when trim(water_meter_reading_docs.relative_path)<>''  OR  trim(water_meter_reading_docs.file_name)<>'' then
                            CONCAT(
                                '$docUrl', '/', 
                                water_meter_reading_docs.relative_path, 
                                '/', water_meter_reading_docs.file_name
                            )
                            else '' 
                            end AS meter_img, 
                        water_meter_reading_docs.relative_path, 
                        water_meter_reading_docs.file_name,
                        users.user_name,
                        ROUND(COALESCE(demands.generate_amount, 0) + COALESCE(demands.arrear_demands, 0) + COALESCE(demands.current_demands, 0)) AS total_amount
                    FROM water_consumer_demands  
                    join demands on demands.demand_id = water_consumer_demands.id
                    LEFT JOIN users on users.id = water_consumer_demands.emp_details_id 
                    left join water_meter_reading_docs on water_meter_reading_docs.demand_id = water_consumer_demands.id
                ),
                owners AS (
                    select water_consumer_owners.consumer_id,
                        string_agg(water_consumer_owners.applicant_name,', ') as applicant_name, 
                        string_agg(water_consumer_owners.guardian_name,', ') as guardian_name, 
                        string_agg(water_consumer_owners.mobile_no,', ') as mobile_no
                    from water_consumer_owners
                    join demands on demands.consumer_id = water_consumer_owners.consumer_id
                    where status = true
                    group by water_consumer_owners.consumer_id
                ),
                last_connections AS (
                    select max(water_consumer_meters.id) as last_id,water_consumer_meters.consumer_id
                    from water_consumer_meters
                    join demands on demands.consumer_id = water_consumer_meters.consumer_id
                    group by water_consumer_meters.consumer_id
                ),
                connection_types AS (
                    select water_consumer_meters.consumer_id,water_consumer_meters.connection_type as current_meter_status,
                        case when water_consumer_meters.connection_type in (1,2) then 'Metered' else 'Fixed' end as connection_type,
                        case when water_consumer_meters.connection_type in (1,2) then water_consumer_meters.meter_no else null end as meter_no
                    from water_consumer_meters
                    join last_connections on last_connections.last_id = water_consumer_meters.id
                )
            ";
            $select = "
            SELECT
            water_second_consumers.id as consumer_id,
            ulb_ward_masters.ward_name AS ward_no, 
            water_second_consumers.id, 
            'water' AS type, 
            water_second_consumers.consumer_no as consumerno, 
            water_second_consumers.user_type as usertype, 
            water_second_consumers.property_no as propertyno, 
            water_second_consumers.address, 
            water_second_consumers.tab_size, 
            water_second_consumers.zone, 
            water_second_consumers.category, 
            water_second_consumers.folio_no as foliono, 
            water_second_consumers.ward_mstr_id,
            owners.applicant_name as applicant_name, 
            owners.guardian_name, 
            owners.mobile_no, 
    
            final_demands.relative_path, 
            final_demands.file_name,
            final_demands.generation_date,
            ROUND(final_demands.generate_amount) as generate_amount, 
            ROUND(final_demands.arrear_demands) as arrear_demands, 
            ROUND(final_demands.current_demands) as current_demands, 
            final_demands.demand_from, 
            final_demands.demand_upto, 
            ROUND(final_demands.total_amount) as total_amount, 
            final_demands.arrear_demand_date, 
            final_demands.current_demand_date, 
            final_demands.user_name ,
            final_demands.demand_type, 
            final_demands.demand_no,
            CASE WHEN upto_reading < from_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as finalreading,
            CASE WHEN from_reading < upto_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as initialreading,
            CONCAT('$NowDate') AS billdate,
            CONCAT('$bilDueDate') AS bildueDate,
            CASE WHEN final_demands.connection_type is null or final_demands.connection_type = 'Fixed' THEN null ELSE connection_types.meter_no END as meter_no,
            CASE WHEN final_demands.connection_type is null THEN 'Fixed' ELSE final_demands.connection_type END as connection_type,
            CASE WHEN final_demands.connection_type != 'Fixed' THEN final_demands.meter_img ELSE null END as meter_img,
            connection_types.current_meter_status,
            zone_masters.zone_name 
        ";
            $from = "
                FROM water_second_consumers 
                JOIN final_demands  ON final_demands.consumer_id = water_second_consumers.id 
                LEFT JOIN owners ON owners.consumer_id = water_second_consumers.id 
                LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id 
                LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id 
                LEFT JOIN connection_types on connection_types.consumer_id = water_second_consumers.id  
                WHERE  1=1
                " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "    
                " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "  
                " . ($metertype ? ($metertype == 3
                ? " AND (connection_types.current_meter_status IN($metertype) OR connection_types.consumer_id IS NULL )"
                : " AND connection_types.current_meter_status IN($metertype)"

            ) : "") . "          
            ";
            $dataSql = $with . $select . $from . " 
                    ORDER BY water_second_consumers.id
                    LIMIT $limit OFFSET $offset ";
            $countSql = $with . " SELECT COUNT(*) " . $from;
            $data = DB::connection('pgsql_water')->select(DB::raw($dataSql));

            $WaterConsumerController = App::makeWith(WaterWaterConsumer::class, ["IConsumer", IConsumer::class]);
            $responseCollection = collect();
            foreach ($data as $val) {

                $request->merge(["consumerId" => $val->consumer_id]);
                $response = $WaterConsumerController->getConsumerDemandsV2($request);
                if (!$response->original["status"]) {
                    continue;
                }
                $response = $response->original["data"];
                $responseCollection->push($response);
            }
            $total = (collect(DB::connection('pgsql_water')->select(DB::raw($countSql)))->first())->count ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $responseCollection,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => ($total > 0 ? $lastPage - 1 : 1),
            ];
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    public function waterBulkdemandV7(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $NowDate    = Carbon::now()->format('Y-m-d');
            $bilDueDate = Carbon::now()->addDays(15)->format('Y-m-d');
            $fromDate    = $uptoDate = Carbon::now()->format("Y-m-d");
            $docUrl      = $this->_docUrl;
            $metertype   = $wardId = $userId = $zoneId = $paymentMode = null;
            $fiYear      = $request->fiYear;
            $quater      = $request->quater;

            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;
            if ($request->quater != null) {
                $refDate = json_decode(getMonthsByQuarter($fiYear, $quater), true);  // Decode the JSON to an array
                $fromDate = $refDate['start_date'];  // First date of the quarter
                $uptoDate = $refDate['end_date'];  // Last date of the quarter

            }

            // if ($request->fromDate) {
            //     $fromDate = $request->fromDate;
            // }
            // if ($request->uptoDate) {
            //     $uptoDate = $request->uptoDate;
            // }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;

            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->metertype == 1) {
                $metertype = '1,2';
            }
            if ($request->metertype == 2) {
                $metertype = '3';
            }
            // Create common SQL logic for fiYear filtering
            $dateCondition = ($fiYear) ?
                ($fromDate ? " AND wd.demand_from >= '$fromDate'" : "") .
                ($uptoDate ? " AND wd.demand_upto <= '$uptoDate'" : "")
                : "";  // This will add the fiYear date filter only if fiYear is provided.

            // Additional conditions when fiYear is null
            $zoneWardMetertypeCondition = (!$fiYear) ?
                ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") .
                ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") .
                ($metertype ?
                    ($metertype == '3'
                        ? " AND (connection_types.current_meter_status IN($metertype) OR connection_types.consumer_id IS NULL)"
                        : " AND connection_types.current_meter_status IN($metertype)"
                    )
                    : "") : "";  // This will apply only if fiYear is null.
            // sum(
            //     wd.due_balance_amount
            // ) as total_amount, 

            $with = "
                    WITH demands AS (
                        SELECT wd.consumer_id, count(wd.id)  , string_agg(wd.connection_type,', ') as demand_type,
                            SUM(wd.due_balance_amount) AS sum_amount, 
                            max(wd.generation_date) as generation_date,
                            MAX(wd.id) AS demand_id ,
                            max(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.final_reading end) as upto_reading,
                            min(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.initial_reading end) as from_reading,
                            MIN(case when wd.is_full_paid=false then wd.demand_from  end ) AS demand_from, 
                            MAX(case when wd.is_full_paid=false then wd.demand_upto  end ) AS demand_upto, 
                        
                            SUM(
                                CASE WHEN wd.consumer_tax_id IS NULL THEN wd.arrear_demand ELSE 0 END
                            ) AS arrear_demands, 
                            SUM(
                                CASE WHEN wd.consumer_tax_id IS NULL THEN wd.current_demand ELSE 0 END
                            ) AS current_demands, 
                            SUM(
                                CASE WHEN wd.consumer_tax_id IS NOT NULL THEN wd.due_balance_amount ELSE 0 END
                            ) AS generate_amount, 
                            max(
                                case when wd.consumer_tax_id is null then wd.arrear_demand_date else null end
                            ) as arrear_demand_date, 
                            max(
                                case when wd.consumer_tax_id is null then wd.current_demand_date else null end
                            ) as current_demand_date 
                        FROM water_consumer_demands wd 
                        left join water_consumer_taxes on water_consumer_taxes.id=  wd.consumer_tax_id
                        WHERE 
    
                        wd.status = TRUE 
                        AND wd.consumer_id IS NOT NULL 
                        AND wd.due_balance_amount>0 
                        $dateCondition
                        GROUP BY 
                        wd.consumer_id 
                    ),
                    final_demands AS(
                        select demands.*,
                            water_consumer_demands.emp_details_id, 
                            water_consumer_demands.status, 
                            water_consumer_demands.demand_no ,
                            water_consumer_demands.connection_type ,
                            CASE when trim(water_meter_reading_docs.relative_path)<>''  OR  trim(water_meter_reading_docs.file_name)<>'' then
                                CONCAT(
                                    '$docUrl', '/', 
                                    water_meter_reading_docs.relative_path, 
                                    '/', water_meter_reading_docs.file_name
                                )
                                else '' 
                                end AS meter_img, 
                            water_meter_reading_docs.relative_path, 
                            water_meter_reading_docs.file_name,
                            users.user_name,
                            ROUND(COALESCE(demands.generate_amount, 0) + COALESCE(demands.arrear_demands, 0) + COALESCE(demands.current_demands, 0)) AS total_amount
                        FROM water_consumer_demands  
                        join demands on demands.demand_id = water_consumer_demands.id
                        LEFT JOIN users on users.id = water_consumer_demands.emp_details_id 
                        left join water_meter_reading_docs on water_meter_reading_docs.demand_id = water_consumer_demands.id
                    ),
                    owners AS (
                        select water_consumer_owners.consumer_id,
                            string_agg(water_consumer_owners.applicant_name,', ') as applicant_name, 
                            string_agg(water_consumer_owners.guardian_name,', ') as guardian_name, 
                            string_agg(water_consumer_owners.mobile_no,', ') as mobile_no
                        from water_consumer_owners
                        join demands on demands.consumer_id = water_consumer_owners.consumer_id
                        where status = true
                        group by water_consumer_owners.consumer_id
                    ),
                    last_connections AS (
                        select max(water_consumer_meters.id) as last_id,water_consumer_meters.consumer_id
                        from water_consumer_meters
                        join demands on demands.consumer_id = water_consumer_meters.consumer_id
                        group by water_consumer_meters.consumer_id
                    ),
                    connection_types AS (
                        select water_consumer_meters.consumer_id,water_consumer_meters.connection_type as current_meter_status,
                            case when water_consumer_meters.connection_type in (1,2) then 'Metered' else 'Fixed' end as connection_type,
                            case when water_consumer_meters.connection_type in (1,2) then water_consumer_meters.meter_no else null end as meter_no
                        from water_consumer_meters
                        join last_connections on last_connections.last_id = water_consumer_meters.id
                    )
                ";
            $select = "
                SELECT
                water_second_consumers.id as consumer_id,
                ulb_ward_masters.ward_name AS ward_no, 
                water_second_consumers.id, 
                'water' AS type, 
                water_second_consumers.consumer_no as consumerno, 
                water_second_consumers.user_type as usertype, 
                water_second_consumers.property_no as propertyno, 
                water_second_consumers.address, 
                water_second_consumers.tab_size, 
                water_second_consumers.zone, 
                water_second_consumers.category, 
                water_second_consumers.folio_no as foliono, 
                water_second_consumers.ward_mstr_id,
                owners.applicant_name as applicant_name, 
                owners.guardian_name, 
                owners.mobile_no, 
        
                final_demands.relative_path, 
                final_demands.file_name,
                final_demands.generation_date,
                ROUND(final_demands.generate_amount) as generate_amount, 
                ROUND(final_demands.arrear_demands) as arrear_demands, 
                ROUND(final_demands.current_demands) as current_demands, 
                final_demands.demand_from, 
                final_demands.demand_upto, 
                ROUND(final_demands.total_amount) as total_amount, 
                final_demands.arrear_demand_date, 
                final_demands.current_demand_date, 
                final_demands.user_name ,
                final_demands.demand_type, 
                final_demands.demand_no,
                CASE WHEN upto_reading < from_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as finalreading,
                CASE WHEN from_reading < upto_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as initialreading,
                CONCAT('$NowDate') AS billdate,
                CONCAT('$bilDueDate') AS bildueDate,
                CASE WHEN final_demands.connection_type is null or final_demands.connection_type = 'Fixed' THEN null ELSE connection_types.meter_no END as meter_no,
                CASE WHEN final_demands.connection_type is null THEN 'Fixed' ELSE final_demands.connection_type END as connection_type,
                CASE WHEN final_demands.connection_type != 'Fixed' THEN final_demands.meter_img ELSE null END as meter_img,
                connection_types.current_meter_status,
                zone_masters.zone_name 
            ";
            $from = "
            FROM water_second_consumers 
            JOIN final_demands ON final_demands.consumer_id = water_second_consumers.id 
            LEFT JOIN owners ON owners.consumer_id = water_second_consumers.id 
            LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id 
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id 
            LEFT JOIN connection_types ON connection_types.consumer_id = water_second_consumers.id  
            WHERE 1=1
            $zoneWardMetertypeCondition
        ";
            $dataSql = $with . $select . $from . " 
                        ORDER BY water_second_consumers.id
                        LIMIT $limit OFFSET $offset ";
            $countSql = $with . " SELECT COUNT(*) " . $from;
            $data = DB::connection('pgsql_water')->select(DB::raw($dataSql));

            $WaterConsumerController = App::makeWith(WaterWaterConsumer::class, ["IConsumer", IConsumer::class]);
            $responseCollection = collect();
            foreach ($data as $val) {

                $request->merge(["consumerId" => $val->consumer_id]);
                $response = $WaterConsumerController->getConsumerDemandsV2($request);
                if (!$response->original["status"]) {
                    continue;
                }
                $response = $response->original["data"];
                $responseCollection->push($response);
            }
            $total = (collect(DB::connection('pgsql_water')->select(DB::raw($countSql)))->first())->count ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $responseCollection,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => ($total > 0 ? $lastPage - 1 : 1),
            ];
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    public function waterBulkdemandV4(colllectionReport $request)
    {
        try {
            $perPage = $request->perPage ? $request->perPage : 50;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $where = "";
            if ($request->wardId) {
                $where .= " AND ward_mstr_id = " . $request->wardId;
            }
            if ($request->zoneId) {
                $where .= " AND zone_mstr_id = " . $request->zoneId;
            }
            $sql = "
                SELECT DISTINCT prop_demands.property_id 
                FROM prop_demands
                JOIN prop_properties on prop_properties.id = prop_demands.property_id
                WHERE prop_demands.status = 1 AND prop_demands.balance>0
                    $where
                GROUP BY prop_demands.property_id
                OFFSET $offset LIMIT $limit
            ";
            $sqlCont = "
                SELECT COUNT(DISTINCT prop_demands.property_id) as count 
                FROM prop_demands
                JOIN prop_properties on prop_properties.id = prop_demands.property_id
                WHERE prop_demands.status = 1 AND prop_demands.balance>0
                    $where
            ";
            $count = (collect(DB::select($sqlCont))->first())->count;
            $data = DB::select($sql);
            $lastPage = ceil($count / $perPage);
            $responseData = collect();
            foreach ($data as $key => $val) {
                $propertyId = $val->property_id;
                $newReq = new Request(["propId" => $propertyId]);
                $response = $this->getHoldingDues($newReq);
                if (!$response->original["status"]) {
                    continue;
                }
                $responseData->push($response->original["data"]);
            }
            $list = [
                "current_page" => $page,
                "last_page" => $lastPage,
                "data" => $responseData,
                "total" => $count,
            ];
            return responseMsgs(true, "data fetched", $list, "011602", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), ['basicDetails' => $basicDtls ?? []], "011602", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }
    /**\
     * bulk payment receipt
     */
    public function bulkReceipt(Request $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $docUrl                     = $this->_docUrl;
            $metertype      = null;
            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $NowDate                    = Carbon::now()->format('Y-m-d');
            $bilDueDate                 = Carbon::now()->addDays(15)->format('Y-m-d');
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            else
                $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::connection('pgsql_water')->enableQueryLog();

            $rawData = ("SELECT 
            water_trans.tran_no AS transactionNo,
            water_trans.tran_date AS transactionDate,
            water_trans.amount AS totalPaidAmount,
            water_trans.payment_type,
            water_trans.payment_mode AS paymentMode,
            ulb_ward_masters.ward_name AS wardNo,
            'water' AS TYPE,
            water_second_consumers.consumer_no AS consumerNo,
            water_second_consumers.bind_book_no AS bindbookno,
            water_second_consumers.user_type,
            water_second_consumers.property_no,
            water_second_consumers.address,
            water_second_consumers.tab_size,
            water_second_consumers.zone,
            water_consumer_owners.applicant_name AS customerName,
            water_consumer_owners.guardian_name,
            water_consumer_owners.mobile_no AS customerMobile,
            water_second_consumers.category,
            water_second_consumers.folio_no,
            water_second_consumers.ward_mstr_id, 
            MAX(water_consumer_initial_meters.initial_reading) AS finalReading,
            MIN(water_consumer_initial_meters.initial_reading) AS initialReading,
            MAX(water_consumer_initial_meters.initial_reading) - MIN(water_consumer_initial_meters.initial_reading) AS unitConsumed,
            zone_masters.zone_name AS zoneName,
            users.name AS empName, 
            MIN(water_consumer_demands.demand_from) AS demand_from,
            MAX(water_consumer_demands.demand_upto) AS demand_upto,
            water_trans.due_amount AS dueAmount,
            ulb_masters.association_with AS association,
            water_second_consumers.book_no AS bookNo
        FROM 
            water_trans
        JOIN water_second_consumers ON water_second_consumers.id = water_trans.related_id
        LEFT JOIN water_consumer_owners ON water_consumer_owners.consumer_id = water_trans.related_id
        LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
        JOIN water_consumer_initial_meters ON water_consumer_initial_meters.consumer_id = water_trans.related_id
        JOIN water_consumer_demands ON water_consumer_demands.consumer_id = water_trans.related_id
        JOIN users ON users.id = water_trans.emp_dtl_id
        JOIN ulb_masters ON ulb_masters.id=water_trans.ulb_id
        WHERE 
             water_trans.emp_dtl_id = $userId
            AND water_trans.tran_date BETWEEN '$fromDate' AND '$uptoDate'
        GROUP BY 
            users.name,
            water_second_consumers.zone,
            water_second_consumers.tab_size,
            water_second_consumers.user_type,
            water_second_consumers.property_no,
            water_second_consumers.address,
            water_trans.id,
            water_second_consumers.consumer_no,
            water_consumer_demands.connection_type,
            water_consumer_demands.status,
            water_consumer_demands.demand_from,
            water_consumer_demands.demand_upto,
            water_trans.amount,
            ulb_ward_masters.ward_name,
            water_consumer_owners.applicant_name,
            water_consumer_owners.guardian_name,
            water_consumer_owners.mobile_no,
            water_second_consumers.category,
            water_second_consumers.folio_no,
            water_second_consumers.ward_mstr_id,
            zone_masters.zone_name,
            water_second_consumers.bind_book_no,
            ulb_masters.association_with,
            water_second_consumers.book_no
        ");

            $data = collect(DB::connection('pgsql_water')->select(DB::raw($rawData)));
            $data1 = $data->map(function ($value) {
                $value->paidAmtInWords = getIndianCurrency($value->totalpaidamount);
                return $value;
            });
            return responseMsgs(true, 'Bulk Receipt', remove_null($data1), '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    /**\
     * bulk receipt 
     */
    public function dateCollectuionReport(Request $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $docUrl                     = $this->_docUrl;
            $metertype      = null;
            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $now                        = Carbon::now();
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDates = $refDate['fromDate'];
            $uptoDates = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            else
                $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            $rawData = ("SELECT 
            subquery.arrear_collections,
            subquery.current_collections,
            COALESCE(subquery.arrear_collections, 0) + COALESCE(subquery.current_collections, 0) AS total_collections, 
            subquery.tran_date
        FROM (
            SELECT 
            SUM(CASE WHEN water_consumer_demands.demand_upto <= '$previousUptoDate' AND water_trans.tran_date >= '$fromDate'  AND water_trans.tran_date <= '$uptoDate' THEN water_trans.amount ELSE 0 END) AS arrear_collections, 
            SUM(CASE WHEN water_consumer_demands.demand_from >= '$fromDates' AND water_consumer_demands.demand_upto <= '$uptoDates' AND water_trans.tran_date >= '$fromDate' AND water_trans.tran_date <= '$uptoDate' THEN water_trans.amount ELSE 0 END) AS current_collections,
 
                   water_trans.tran_date
             
             FROM 
                water_trans
            JOIN water_consumer_demands ON water_consumer_demands.consumer_id = water_trans.related_id
           LEFT  JOIN users ON users.id = water_trans.emp_dtl_id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_trans.ward_id
            LEFT JOIN water_second_consumers AS related_consumers ON related_consumers.id = water_trans.related_id
            WHERE 
                water_consumer_demands.paid_status = 1
                AND water_trans.status = 1 
                " . ($zoneId ? " AND  related_consumers.zone_mstr_id = $zoneId" : "") . "
                " . ($wardId ? " AND related_consumers.ward_mstr_id = $wardId" : "") . "
                 " . ($userId ? " AND water_trans.emp_dtl_id = $userId" : "") . "
                GROUP BY 
                water_trans.tran_date
        ) AS subquery
           ");

            $data = DB::connection('pgsql_water')->select(DB::raw($rawData));
            $refData = collect($data);

            $refDetailsV2 = [
                "array" => $data,
                "sum_current_coll" => $refData->pluck('current_collections')->sum(),
                "sum_arrear_coll" => $refData->pluck('arrear_collections')->sum(),
                "sum_total_coll" => $refData->pluck('total_collections')->sum()
            ];
            // $data1 = $data->map(function ($value) {
            //     $value->paidAmtInWords = getIndianCurrency($value->totalpaidamount);
            //     return $value;
            // });
            return responseMsgs(true, 'collection report', remove_null($refDetailsV2), '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | water Collection report 
     */
    public function tcCollectionReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $now                        = Carbon::now()->format('Y-m-d');
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $now                        = Carbon::now();
            $currentDate                = Carbon::now()->format('Y-m-d');
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDates = $refDate['fromDate'];
            $uptoDates = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::enableQueryLog();
            $data = ("SELECT 
              subquery.tran_id,
              subquery.tran_no,
              COALESCE(subquery.arrear_collections, 0) AS arrear_collections,
              COALESCE(subquery.current_collections, 0) AS current_collections,
              COALESCE(subquery.arrear_collections, 0) + COALESCE(subquery.current_collections, 0) AS total_collections,
              subquery.consumer_no,
              subquery.ward_name,
              subquery.zone_name,
              subquery.amount,
              subquery.address,
              subquery.transactiondate,
              subquery.user_name,
              subquery.payment_type,
              subquery.paymentstatus,
              subquery.applicant_name
              
     FROM (
         SELECT 
            --  SUM(CASE WHEN water_consumer_collections.demand_from  <= '$previousUptoDate'   THEN water_consumer_collections.paid_amount ELSE 0 END) AS arrear_collections, 
            --  SUM(CASE WHEN water_consumer_collections.demand_upto >=  '$fromDates'   AND water_trans.tran_date >= '$fromDates' THEN water_consumer_collections.paid_amount ELSE 0 END) AS current_collections,
                water_trans.id as tran_id,
                water_trans.tran_date,
                water_trans.tran_no,
                water_second_consumers.consumer_no,water_trans.payment_mode,
                water_trans.amount,
                ulb_ward_masters.ward_name,
                zone_masters.zone_name,
                water_second_consumers.address,
                water_trans.tran_date AS transactiondate,
                users.user_name,
                users.name,
                water_trans.payment_type,
                water_trans.payment_mode AS paymentstatus,
                water_consumer_owners.applicant_name,

              --  Arrear Collections: Payments for demands up to the previous financial year
        SUM(CASE 
            WHEN water_consumer_collections.demand_upto <= '$previousUptoDate' THEN water_consumer_collections.paid_amount 
            ELSE 0 
        END) AS arrear_collections,

        -- Current Collections: Payments for demands in the current financial year
        SUM(CASE 
            WHEN water_consumer_collections.demand_from >= '$fromDates' AND water_consumer_collections.demand_upto <= '$uptoDates' 
            THEN water_consumer_collections.paid_amount 
            ELSE 0 
        END) AS current_collections
          
        FROM water_trans 
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id=water_trans.ward_id
        LEFT JOIN water_second_consumers ON water_second_consumers.id=water_trans.related_id
        left JOIN water_consumer_owners ON water_consumer_owners.consumer_id=water_trans.related_id
        -- JOIN water_consumer_demands ON water_consumer_demands.consumer_id=water_trans.related_id
        left Join zone_masters on zone_masters.id= water_second_consumers.zone_mstr_id
        JOIN water_consumer_collections on water_consumer_collections.transaction_id = water_trans.id
        LEFT JOIN users ON users.id=water_trans.emp_dtl_id
        where water_trans.related_id is not null 
        and water_trans.status in (1, 2) 
       -- and tran_type = 'Demand Collection'
        and water_trans.tran_date between '$fromDate' and '$uptoDate'
                    " . ($zoneId ? " AND  water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                    " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                    " . ($userId ? " AND water_trans.emp_dtl_id = $userId" : "") . "
                    " . ($paymentMode ? " AND water_trans.payment_mode = '$paymentMode'" : "") . "
            --AND water_consumer_demands.paid_status=1
        GROUP BY 
                water_trans.id,
                    water_second_consumers.consumer_no,
                    ulb_ward_masters.ward_name,
                    zone_masters.zone_name,
                    water_second_consumers.address,
                    users.user_name,
                    users.name,
                    water_consumer_owners.applicant_name,
                    water_trans.payment_type
     ) AS subquery");
            $data = DB::connection('pgsql_water')->select(DB::raw($data));
            $refData = collect($data);

            $refDetailsV2 = [
                "array" => $data,
                "sum_current_coll" => roundFigure($refData->pluck('current_collections')->sum() ?? 0),
                "sum_arrear_coll" => roundFigure($refData->pluck('arrear_collections')->sum() ?? 0),
                "sum_total_coll" => roundFigure($refData->pluck('total_collections')->sum() ?? 0),
                "totalAmount"   =>  roundFigure($refData->pluck('amount')->sum() ?? 0),
                "totalColletion" => $refData->pluck('tran_id')->count(),
                "currentDate"  => $currentDate
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "collection Report", $refDetailsV2, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function tcvisitRecords(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $now                        = Carbon::now()->format('Y-m-d');
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $now                        = Carbon::now();
            $currentDate                = Carbon::now()->format('Y-m-d');
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDates = $refDate['fromDate'];
            $uptoDates = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            // -- round(water_consumer_demands.arrear_demands) as arrear_demand,
            // subquery.total_amount,
            // DB::enableQueryLog();
            $rawData = ("select 
                       final_data.*,readings.initial_reading,readings.final_reading,
                       (COALESCE(readings.final_reading,0) - COALESCE(readings.initial_reading,0)) as consumpsun_unit,  
                       readings.created_on,
                       round(subquery.generate_amount)as generate_amount,
                       round(subquery.arrear_demands) as arrear_demands,
                       round(subquery.current_demands) as current_demands,
                       round(COALESCE(subquery.generate_amount, 0) + COALESCE(subquery.arrear_demands, 0) + COALESCE(subquery.current_demands,0)) AS total_amount,
                       subquery.demand_from,
                       subquery.demand_upto,
                      
                       subquery.arrear_demand_date,
                       subquery.current_demand_date
                      from (
                          SELECT water_second_consumers.id as consumer_id,
                          water_second_consumers.consumer_no,
                          water_second_consumers.address,
                          water_second_consumers.meter_no,
                          water_second_consumers.mobile_no,
                          round(water_consumer_demands.current_demand) as current_demand,
                         
                          round(water_consumer_demands.due_balance_amount) as due_balance_amount,
                          water_consumer_demands.demand_from,
                          water_consumer_demands.demand_upto,
                          water_consumer_demands.total_records,
                          water_consumer_demands.total_visit,
                          water_consumer_demands.emp_details_id,
                          water_consumer_demands.total_emp,
                          water_consumer_demands.user_name,
                          water_consumer_owners.applicant_name,         
                          ulb_ward_masters.ward_name AS ward_no,
                          zone_masters.zone_name
                          FROM water_second_consumers  
                      JOIN (
                         SELECT 
                          SUM(CASE WHEN water_consumer_demands.generation_date >= '$previousUptoDate' THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS current_demand,
                         SUM(CASE WHEN water_consumer_demands.generation_date <=  '$previousUptoDate' THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS arrear_demands,
                         SUM(water_consumer_demands.due_balance_amount) AS due_balance_amount,
                         MIN(demand_from) AS demand_from,
                         MAX(demand_upto) AS demand_upto,
                         COUNT(water_consumer_demands.emp_details_id) AS total_records,
                         COUNT(distinct(water_consumer_demands.emp_details_id)) AS total_emp,
                         COUNT(distinct(water_consumer_demands.consumer_id)) as total_visit,
                         string_agg(distinct(water_consumer_demands.emp_details_id)::text,', ')as emp_details_id,
                        string_agg(distinct(users.user_name),', ')as user_name,
                         water_consumer_demands.consumer_id
                  FROM water_consumer_demands	
                  left JOIN users ON users.id = water_consumer_demands.emp_details_id
                  WHERE water_consumer_demands.status =true 
                  AND water_consumer_demands.generation_date BETWEEN '$fromDate' AND '$uptoDate'
                  " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                  " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                  " . ($userId ? " AND water_consumer_demands.emp_details_id = $userId" : "") . "
                  GROUP BY consumer_id	      
              )water_consumer_demands ON water_second_consumers.id = water_consumer_demands.consumer_id	
            -- left JOIN users ON users.id = ANY(STRING_TO_ARRAY(water_consumer_demands.emp_details_id,',')::bigint[])
            left JOIN (
                  select string_agg(water_consumer_owners.applicant_name,',') AS applicant_name,
                      water_consumer_owners.consumer_id
                  FROM water_consumer_owners
                  GROUP BY consumer_id
              )water_consumer_owners ON water_consumer_owners.consumer_id = water_consumer_demands.consumer_id
            LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
            ) AS final_data
              LEFT JOIN (
           SELECT 
               water_consumer_demands.consumer_id,
               created_on,
               MIN(initial_reading) AS initial_reading,
               MAX(final_reading) AS final_reading
               FROM water_consumer_taxes 
               JOIN water_consumer_demands ON water_consumer_demands.consumer_tax_id = water_consumer_taxes.id
               WHERE 
               water_consumer_demands.generation_date BETWEEN '$fromDate' AND '$uptoDate'
               " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
               " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
               " . ($userId ? " AND water_consumer_demands.emp_details_id = $userId" : "") . "
               AND water_consumer_demands.status = TRUE 
               " . ($userId ? " AND water_consumer_demands.emp_details_id = $userId" : "") . "
               GROUP BY water_consumer_demands.consumer_id, created_on
               ) AS readings ON readings.consumer_id = final_data.consumer_id
               LEFT JOIN (
                SELECT 
                    distinct consumer_id,
                    MIN(demand_from) AS demand_from,
                    MAX(demand_upto) AS demand_upto,
                    sum(water_consumer_demands.due_balance_amount) as total_amount,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.arrear_demand ELSE 0 END) AS arrear_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.current_demand ELSE 0 END) AS current_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NOT NULL THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS generate_amount,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.arrear_demand_date else null end ) as arrear_demand_date,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.current_demand_date else null end ) as current_demand_date
                FROM water_consumer_demands
                GROUP BY consumer_id
                ORDER BY water_consumer_demands.consumer_id 
            ) AS subquery ON subquery.consumer_id = final_data.consumer_id
             ");

            $data = DB::connection('pgsql_water')->select($rawData . " limit $limit offset $offset");

            $count = (collect(DB::connection('pgsql_water')->SELECT("SELECT COUNT(*) AS total
              FROM ($rawData) total"))->first());

            $count = (collect(DB::connection('pgsql_water')->SELECT("SELECT COUNT(*)AS total, SUM(due_balance_amount) AS total_amount FROM ($rawData) total"))->first());
            $total = ($count)->total ?? 0;

            $lastPage = ceil($total / $perPage);

            $refData = collect($data);
            $refDetailsV2 = [
                "data" => $data,
                "sum_current_coll" => $refData->pluck('current_collections')->sum(),
                "sum_arrear_coll" => $refData->pluck('arrear_demand')->sum(),
                "sum_total_coll"  => $refData->pluck('total_collections')->sum(),
                "totalAmount"     =>  round($refData->pluck('due_balance_amount')->sum()),
                "currentDate"     => $currentDate,
                "current_page"    => $page,
                "per_page"        => $perPage,
                "last_page"       => $lastPage,
                "total"           => $total
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "visit Report", $refDetailsV2, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    # tc visit record with out pagination 
    public function tcvisitRecordsv2(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $now                        = Carbon::now()->format('Y-m-d');
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $now                        = Carbon::now();
            $currentDate                = Carbon::now()->format('Y-m-d');
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDates = $refDate['fromDate'];
            $uptoDates = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            // round(subquery.total_amount) as total_amount
            // DB::enableQueryLog();
            $rawData = ("select 
                       final_data.*,readings.initial_reading,readings.final_reading,
                       (COALESCE(readings.final_reading,0) - COALESCE(readings.initial_reading,0)) as consumpsun_unit,  
                       readings.created_on,
                       round(subquery.generate_amount)as generate_amount,
                       round(subquery.arrear_demands) as arrear_demands,
                       round(subquery.current_demands) as current_demands,
                       round(COALESCE(subquery.generate_amount, 0) + COALESCE(subquery.arrear_demands, 0) + COALESCE(subquery.current_demands,0)) AS total_amount,
                       subquery.demand_from,
                       subquery.demand_upto,
                       subquery.arrear_demand_date,
                       subquery.current_demand_date
                      from (
                          SELECT water_second_consumers.id as consumer_id,
                          water_second_consumers.consumer_no,
                          water_second_consumers.address,
                          water_second_consumers.mobile_no,
                          water_consumer_demands.current_demand,
                          water_consumer_demands.due_balance_amount,
                          water_consumer_demands.demand_from,
                          water_consumer_demands.demand_upto,
                          water_consumer_demands.total_records,
                          water_consumer_demands.emp_details_id,
                          water_consumer_demands.total_emp,
                          water_consumer_demands.user_name,
                          water_consumer_owners.applicant_name,         
                          ulb_ward_masters.ward_name AS ward_no,
                          zone_masters.zone_name
                          FROM water_second_consumers  
                      JOIN (
                         SELECT 
                          SUM(CASE WHEN water_consumer_demands.generation_date >= '$previousUptoDate' THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS current_demand,
                         SUM(CASE WHEN water_consumer_demands.generation_date <=  '$previousUptoDate' THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS arrear_demands,
                         round(SUM(water_consumer_demands.due_balance_amount)) AS due_balance_amount,
                         MIN(demand_from) AS demand_from,
                         MAX(demand_upto) AS demand_upto,
                         COUNT(water_consumer_demands.emp_details_id) AS total_records,
                         COUNT(distinct(water_consumer_demands.emp_details_id)) AS total_emp,
                         string_agg(distinct(water_consumer_demands.emp_details_id)::text,', ')as emp_details_id,
                        string_agg(distinct(users.user_name),', ')as user_name,
                         water_consumer_demands.consumer_id
                  FROM water_consumer_demands	
                  left JOIN users ON users.id = water_consumer_demands.emp_details_id
                  WHERE water_consumer_demands.status =true 
                  AND water_consumer_demands.generation_date BETWEEN '$fromDate' AND '$uptoDate'
                  " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                  " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                  " . ($userId ? " AND water_consumer_demands.emp_details_id = $userId" : "") . "
                  GROUP BY consumer_id	      
              )water_consumer_demands ON water_second_consumers.id = water_consumer_demands.consumer_id	
            -- left JOIN users ON users.id = ANY(STRING_TO_ARRAY(water_consumer_demands.emp_details_id,',')::bigint[])
            left JOIN (
                  select string_agg(water_consumer_owners.applicant_name,',') AS applicant_name,
                      water_consumer_owners.consumer_id
                  FROM water_consumer_owners
                  GROUP BY consumer_id
              )water_consumer_owners ON water_consumer_owners.consumer_id = water_consumer_demands.consumer_id
            LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id
            ) AS final_data
              LEFT JOIN (
           SELECT 
               water_consumer_demands.consumer_id,
               created_on,
               MIN(initial_reading) AS initial_reading,
               MAX(final_reading) AS final_reading
               FROM water_consumer_taxes 
               JOIN water_consumer_demands ON water_consumer_demands.consumer_tax_id = water_consumer_taxes.id
               WHERE 
               water_consumer_demands.generation_date BETWEEN '$fromDate' AND '$uptoDate'
               AND water_consumer_demands.status = TRUE 
               " . ($userId ? " AND water_consumer_demands.emp_details_id = $userId" : "") . "
               GROUP BY water_consumer_demands.consumer_id, created_on
               ) AS readings ON readings.consumer_id = final_data.consumer_id
               LEFT JOIN (
                SELECT 
                    distinct consumer_id,
                    MIN(demand_from) AS demand_from,
                    MAX(demand_upto) AS demand_upto,
                    sum(water_consumer_demands.due_balance_amount) as total_amount,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.arrear_demand ELSE 0 END) AS arrear_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NULL THEN water_consumer_demands.current_demand ELSE 0 END) AS current_demands,
                    SUM(CASE WHEN water_consumer_demands.consumer_tax_id IS NOT NULL THEN water_consumer_demands.due_balance_amount ELSE 0 END) AS generate_amount,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.arrear_demand_date else null end ) as arrear_demand_date,
                    max(case when water_consumer_demands.consumer_tax_id is null then water_consumer_demands.current_demand_date else null end ) as current_demand_date
                FROM water_consumer_demands
                GROUP BY consumer_id
                ORDER BY water_consumer_demands.consumer_id 
            ) AS subquery ON subquery.consumer_id = final_data.consumer_id;
             ");
            $data = DB::connection('pgsql_water')->select(DB::raw($rawData));
            $refData = collect($data);
            $refDetailsV2 = [
                "array" => $data,
                "sum_current_coll" => $refData->pluck('current_collections')->sum(),
                "sum_arrear_coll" => $refData->pluck('arrear_demand')->sum(),
                "sum_total_coll" => $refData->pluck('total_collections')->sum(),
                "totalAmount"   =>  round($refData->pluck('due_balance_amount')->sum()),
                "totalCollection" => $refData->pluck('consumer_id')->count(),
                "currentDate"  => $currentDate
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "visit Report", $refDetailsV2, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    /**
     * this function for demand bill sheet 
     */
    public function waterBulkdemandV3(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $NowDate    = Carbon::now()->format('Y-m-d');
            $bilDueDate = Carbon::now()->addDays(15)->format('Y-m-d');
            $fromDate    = $uptoDate = Carbon::now()->format("Y-m-d");
            $docUrl      = $this->_docUrl;
            $metertype   = $wardId = $userId = $zoneId = $paymentMode = null;

            // $perPage = $request->perPage ? $request->perPage : 10;
            // $page = $request->page && $request->page > 0 ? $request->page : 1;
            // $limit = $perPage;
            // $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;

            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->metertype == 1) {
                $metertype = '1,2';
            }
            if ($request->metertype == 2) {
                $metertype = '3';
            }
            // sum(
            //     wd.due_balance_amount
            // ) as total_amount,

            $with = "
                WITH demands AS (
                    SELECT wd.consumer_id, count(wd.id)  , string_agg(wd.connection_type,', ') as demand_type,
                        SUM(wd.due_balance_amount) AS sum_amount, 
                        max(wd.generation_date) as generation_date,
                        MAX(wd.id) AS demand_id ,
                        max(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.final_reading end) as upto_reading,
                        min(case when water_consumer_taxes.id is null then wd.current_meter_reading else water_consumer_taxes.initial_reading end) as from_reading,
                        MIN(wd.demand_from) AS demand_from, 
                        MAX(wd.demand_upto) AS demand_upto,
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NULL THEN wd.arrear_demand ELSE 0 END
                        ) AS arrear_demands, 
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NULL THEN wd.current_demand ELSE 0 END
                        ) AS current_demands, 
                        SUM(
                            CASE WHEN wd.consumer_tax_id IS NOT NULL THEN wd.due_balance_amount ELSE 0 END
                        ) AS generate_amount, 
                        max(
                            case when wd.consumer_tax_id is null then wd.arrear_demand_date else null end
                        ) as arrear_demand_date, 
                        max(
                            case when wd.consumer_tax_id is null then wd.current_demand_date else null end
                        ) as current_demand_date 
                    FROM water_consumer_demands wd 
                    left join water_consumer_taxes on water_consumer_taxes.id=  wd.consumer_tax_id
                    WHERE 
                    wd.status = TRUE 
                    AND wd.consumer_id IS NOT NULL 
                    AND wd.due_balance_amount>0 
                    GROUP BY 
                    wd.consumer_id 
                ),
                final_demands AS(
                    select demands.*,
                        water_consumer_demands.emp_details_id, 
                        water_consumer_demands.status, 
                        water_consumer_demands.demand_no ,
                        water_consumer_demands.connection_type ,
                        CASE when trim(water_meter_reading_docs.relative_path)<>''  OR  trim(water_meter_reading_docs.file_name)<>'' then
                            CONCAT(
                                '$docUrl', '/', 
                                water_meter_reading_docs.relative_path, 
                                '/', water_meter_reading_docs.file_name
                            )
                            else '' 
                            end AS meter_img, 
                        water_meter_reading_docs.relative_path, 
                        water_meter_reading_docs.file_name,
                        users.user_name ,
                        ROUND(COALESCE(demands.generate_amount, 0) + COALESCE(demands.arrear_demands, 0) + COALESCE(demands.current_demands, 0)) AS total_amount
                    FROM water_consumer_demands  
                    join demands on demands.demand_id = water_consumer_demands.id
                    LEFT JOIN users on users.id = water_consumer_demands.emp_details_id 
                    left join water_meter_reading_docs on water_meter_reading_docs.demand_id = water_consumer_demands.id
                ),
                owners AS (
                    select water_consumer_owners.consumer_id,
                        string_agg(water_consumer_owners.applicant_name,', ') as applicant_name, 
                        string_agg(water_consumer_owners.guardian_name,', ') as guardian_name, 
                        string_agg(water_consumer_owners.mobile_no,', ') as mobile_no
                    from water_consumer_owners
                    join demands on demands.consumer_id = water_consumer_owners.consumer_id
                    where status = true
                    group by water_consumer_owners.consumer_id
                ),
                last_connections AS (
                    select max(water_consumer_meters.id) as last_id,water_consumer_meters.consumer_id
                    from water_consumer_meters
                    join demands on demands.consumer_id = water_consumer_meters.consumer_id
                    group by water_consumer_meters.consumer_id
                ),
                connection_types AS (
                    select water_consumer_meters.consumer_id,water_consumer_meters.connection_type as current_meter_status,
                        case when water_consumer_meters.connection_type in (1,2) then 'Metered' else 'Fixed' end as connection_type,
                        case when water_consumer_meters.connection_type in (1,2) then water_consumer_meters.meter_no else null end as meter_no
                    from water_consumer_meters
                    join last_connections on last_connections.last_id = water_consumer_meters.id
                )
            ";
            $select = "
            SELECT
                water_second_consumers.id as consumer_id,
                ulb_ward_masters.ward_name AS ward_no, 
                water_second_consumers.id, 
                'water' AS type, 
                water_second_consumers.consumer_no as consumerno, 
                water_second_consumers.user_type as usertype, 
                water_second_consumers.property_no as propertyno, 
                water_second_consumers.address, 
                water_second_consumers.tab_size, 
                water_second_consumers.zone, 
                water_second_consumers.category, 
                water_second_consumers.folio_no as foliono, 
                water_second_consumers.ward_mstr_id,
                owners.applicant_name as applicant_name, 
                owners.guardian_name, 
                owners.mobile_no, 
        
                final_demands.relative_path, 
                final_demands.file_name,
                final_demands.generation_date,
                ROUND(final_demands.generate_amount) as generate_amount, 
                ROUND(final_demands.arrear_demands) as arrear_demands, 
                ROUND(final_demands.current_demands) as current_demands, 
                final_demands.demand_from, 
                final_demands.demand_upto, 
                ROUND(final_demands.total_amount) as total_amount, 
                final_demands.arrear_demand_date, 
                final_demands.current_demand_date, 
                final_demands.user_name ,
                final_demands.demand_type, 
                final_demands.demand_no,
                CASE WHEN upto_reading < from_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as finalreading,
                CASE WHEN from_reading < upto_reading THEN ROUND(from_reading) ELSE ROUND(upto_reading) END as initialreading,
                CONCAT('$NowDate') AS billdate,
                CONCAT('$bilDueDate') AS bildueDate,
                CASE WHEN final_demands.connection_type is null or final_demands.connection_type = 'Fixed' THEN null ELSE connection_types.meter_no END as meter_no,
                CASE WHEN final_demands.connection_type is null THEN 'Fixed' ELSE final_demands.connection_type END as connection_type,
                CASE WHEN final_demands.connection_type != 'Fixed' THEN final_demands.meter_img ELSE null END as meter_img,
                connection_types.current_meter_status,
                zone_masters.zone_name 
            ";
            $from = "
                FROM water_second_consumers 
                JOIN final_demands  ON final_demands.consumer_id = water_second_consumers.id 
                LEFT JOIN owners ON owners.consumer_id = water_second_consumers.id 
                LEFT JOIN zone_masters ON zone_masters.id = water_second_consumers.zone_mstr_id 
                LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = water_second_consumers.ward_mstr_id 
                LEFT JOIN connection_types on connection_types.consumer_id = water_second_consumers.id  
                WHERE  1=1
                " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "    
                " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "  
                " . ($metertype ? ($metertype == 3
                ? " AND (connection_types.current_meter_status IN($metertype) OR connection_types.consumer_id IS NULL )"
                : " AND connection_types.current_meter_status IN($metertype)"
            ) : "") . "          
            ";
            $dataSql = $with . $select . $from . " ORDER BY water_second_consumers.id";
            $data = DB::connection('pgsql_water')->select(DB::raw($dataSql));
            $total = count($data);
            $list = [
                "data" => $data,
                "total" => $total,
            ];
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime = NULL, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function tranDeactivatedList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "appType" => "nullable|in:PROPERTY,SAF",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        return $this->ReportRepository->tranDeactivatedList($request);
    }

    public function deviceTypeCollection(Request $request)
    {
        try {
            $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
            $deviceType = 'android';
            $paymentMode = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->deviceType) {
                $deviceType = $request->deviceType;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }

            $data = WaterTran::select(DB::raw("COALESCE(sum(amount),0) as total_amount,count(id)total_tran"))
                ->whereBetween("tran_date", [$fromDate, $uptoDate])
                ->whereIn("status", [1, 2])
                ->whereNotNull("device_type")
                ->where("device_type", $deviceType);
            if ($paymentMode) {
                $data->where(DB::raw("upper(payment_mode)", strtoupper($paymentMode)));
            }
            $data = $data->first();
            return responseMsgs(true, "data fetched", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }
    /**
     * |consumer Demand Due Report
     * | Arshad 
     */
    // public function consumeDemandDuesReport(Request $request)
    // {
    //     $now = Carbon::now()->format("Y-m-d");
    //     $validated = Validator::make(
    //         $request->all(),
    //         [
    //             // "fromDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
    //             "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
    //             "userId" => "nullable|digits_between:1,9223372036854775807",
    //             "wardId" => "nullable|digits_between:1,9223372036854775807",
    //             "zoneId" => "nullable|digits_between:1,9223372036854775807",
    //             "page" => "nullable|digits_between:1,9223372036854775807",
    //             "perPage" => "nullable|digits_between:1,9223372036854775807",
    //         ]
    //     );
    //     if ($validated->fails()) {
    //         return validationErrorV2($validated);
    //     }

    //     try {
    //         $fromDate = $uptoDate = $now;
    //         $userId = $wardId = $zoneId = null;
    //         // $key = $request->key;

    //         // if ($key) {
    //         //     $fromDate = $uptoDate = null;
    //         // }
    //         if ($request->fromDate) {
    //             $fromDate = $request->fromDate;
    //         }
    //         if ($request->uptoDate) {
    //             $uptoDate = $request->uptoDate;
    //         }
    //         if ($request->wardId) {
    //             $wardId = $request->wardId;
    //         }
    //         if ($request->zoneId) {
    //             $zoneId = $request->zoneId;
    //         }
    //         if ($request->userId) {
    //             $userId = $request->userId;
    //         }
    //         // Original query for detailed information
    //         $data = waterConsumerDemand::select(
    //             "water_second_consumers.id",
    //             "water_consumer_demands.id as demandId",
    //             "water_consumer_demands.consumer_id",
    //             "water_second_consumers.consumer_no",
    //             "water_second_consumers.folio_no as property_no",
    //             "zone_masters.zone_name",
    //             "ulb_ward_masters.ward_name",
    //             "owners.applicant_name",
    //             "owners.guardian_name",
    //             "owners.mobile_no",
    //             "water_second_consumers.category",
    //             "water_property_type_mstrs.property_type",
    //             "water_consumer_demands.amount",
    //             "water_consumer_demands.demand_from",
    //             "water_consumer_demands.demand_upto",
    //             "water_consumer_demands.is_full_paid"
    //             // "users.name AS user_name",
    //         )
    //             // ->leftJoin("users", "users.id", "water_consumer_demands.emp_details_id")
    //             ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
    //             ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
    //             ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
    //             ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
    //             ->Join('water_consumer_owners as owners', 'owners.id', 'water_consumer_demands.consumer_id')
    //             ->where('water_consumer_demands.is_full_paid', false)
    //             ->where('water_consumer_demands.amount', "<>", 0);
    //         if ($uptoDate) {
    //             // $data->where('demand_from', '>=', $fromDate)
    //             $data->where('demand_upto', '<=', $uptoDate);
    //         }
    //         if ($userId) {
    //             $data->where('water_consumer_demands.emp_details_id', $userId);
    //         }
    //         if ($wardId) {
    //             $data->where('water_second_consumers.ward_mstr_id', $wardId);
    //         }
    //         if ($zoneId) {
    //             $data->where('water_second_consumers.zone_mstr_id', $zoneId);
    //         }

    //         $perPage = $request->perPage ? $request->perPage : 10;
    //         $paginator = $data->paginate($perPage);
    //         $list = [
    //             "current_page" => $paginator->currentPage(),
    //             "last_page" => $paginator->lastPage(),
    //             "data" => $paginator->items(),
    //             "total" => $paginator->total(),
    //             // "unpaid_consumers_count" => $unpaidConsumersCount
    //         ];

    //         $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
    //         return responseMsgs(true, "", $list);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "");
    //     }
    // }

    public function consumeDemandDuesReport(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");

        $validated = Validator::make(
            $request->all(),
            [
                // 'filterBy'  => 'required',
                // 'parameter' => 'required',
                "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
                "propertyType" => "required"
            ]
        );

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }

        try {
            $uptoDate = $request->uptoDate ?? $now;
            $userId = $request->userId;
            $wardId = $request->wardId;
            $zoneId = $request->zoneId;
            $paramenter     = $request->parameter;
            $key            = $request->filterBy;
            $propertyType   = $request->propertyType;
            $string         = preg_replace("/([A-Z])/", "_$1", $key);
            $refstring      = strtolower($string);
            $data = waterConsumerDemand::select(
                "water_second_consumers.id as consumer_id",
                "water_second_consumers.consumer_no",
                "water_second_consumers.address",
                "water_second_consumers.folio_no as property_no",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "water_second_consumers.category",
                "water_property_type_mstrs.property_type",
                DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount'),
                DB::raw('MIN(water_consumer_demands.demand_from) as earliest_demand_from'),
                DB::raw('MAX(water_consumer_demands.demand_upto) as latest_demand_upto'),
                "water_second_consumers.notice"
            )
                ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftJoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
                ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
                ->join('water_consumer_owners as owners', 'owners.id', 'water_consumer_demands.consumer_id')
                ->where('water_consumer_demands.is_full_paid', false)
                ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
                ->where('water_second_consumers.generated', false)
                ->where('water_second_consumers.status', 1)
                // ->where('water_second_consumers.' . $refstring, 'LIKE', '%' . $paramenter . '%')
                ->groupBy(
                    'water_second_consumers.id',
                    'water_second_consumers.consumer_no',
                    'water_second_consumers.folio_no',
                    'zone_masters.zone_name',
                    'ulb_ward_masters.ward_name',
                    'owners.applicant_name',
                    'owners.guardian_name',
                    'owners.mobile_no',
                    'water_second_consumers.category',
                    'water_property_type_mstrs.property_type'
                );
            // Apply condition based on propertyType
            if ($propertyType == 1) {
                $data->havingRaw('SUM(water_consumer_demands.due_balance_amount) >= 10000');
            } elseif ($propertyType == 2) {
                $data->havingRaw('SUM(water_consumer_demands.due_balance_amount) >= 25000');
            }

            if ($userId) {
                $data->where('water_consumer_demands.emp_details_id', $userId);
            }
            if ($wardId) {
                $data->where('water_second_consumers.ward_mstr_id', $wardId);
            }
            if ($zoneId) {
                $data->where('water_second_consumers.zone_mstr_id', $zoneId);
            }
            if ($propertyType) {
                $data->where('water_second_consumers.property_type_id', $propertyType);
            }

            $perPage = $request->perPage ?? 10;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    /**
     * |generate notice on unpaid demand 
     */
    // public function generateNotice(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'consumerId' => 'required|array',
    //         'consumerId.*' => 'integer',
    //         'generated' => 'required|boolean',
    //         'notice' => 'required|integer|in:1,2,3'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation error',
    //             'errors' => $validator->errors()
    //         ], 200);
    //     }

    //     $noticeNos = [];

    //     try {
    //         $refConParamId = Config::get('waterConstaint.PARAM_IDS');
    //         $generated = $request->generated;
    //         $noticeType =  $request->notice;

    //         // Update generated status for the consumers
    //         WaterSecondConsumer::whereIn('id', $request->consumerId)
    //             ->update([
    //                 'generated' => $generated
    //             ]);

    //         $mWaterConsumer = WaterSecondConsumer::whereIn('id', $request->consumerId)
    //             ->get();

    //         foreach ($mWaterConsumer as $water) {
    //             $idGeneration = new PrefixIdGenerator($refConParamId['AMCN'], 2);
    //             $noticeNo = $idGeneration->generate();
    //             switch ($noticeType) {
    //                 case 1:
    //                     $water->update([
    //                         'notice_no_1' => $noticeNo, 'notice' => $noticeType
    //                     ]);
    //                     break;
    //                 case 2:
    //                     $water->update(['notice_no_2' => $noticeNo, 'notice_2' => $noticeType]);
    //                     break;
    //                 case 3:
    //                     $water->update(['notice_no_3' => $noticeNo, 'notice_3' => $noticeType]);
    //                     break;
    //             }

    //             $noticeNos[$water->id] = $noticeNo;
    //         }

    //         return responseMsgs(true, "", $noticeNos);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "");
    //     }
    // }

    // public function generateNotice(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'consumerId' => 'required|array',
    //         'consumerId.*' => 'integer',
    //         'generated' => 'required|boolean',
    //         'notice' => 'required|integer|in:1,2,3',
    //         'demandFrom' => 'required|array',
    //         'demandFrom.*' => 'date',
    //         'demandUpto' => 'required|array',
    //         'demandUpto.*' => 'date',
    //         'amount' => 'required|array',
    //         'amount.*' => 'numeric',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation error',
    //             'errors' => $validator->errors()
    //         ], 200);
    //     }

    //     $noticeNos = [];
    //     $consumerIds = $request->consumerId;
    //     $noticeType = $request->notice;
    //     $now = Carbon::now()->format("Y-m-d");
    //     // Initialize arrays to track existing notices
    //     $existingNotices = [
    //         1 => [],
    //         2 => [],
    //         3 => []
    //     ];

    //     try {
    //         $refConParamId = Config::get('waterConstaint.PARAM_IDS');
    //         $generated = $request->generated;

    //         // Fetch consumers based on IDs
    //         $consumers = WaterSecondConsumer::whereIn('id', $consumerIds)->get();

    //         // Check for existing notices
    //         foreach ($consumers as $consumer) {
    //             switch ($noticeType) {
    //                 case 1:
    //                     if (!empty($consumer->notice_no_1)) {
    //                         $existingNotices[1][] = $consumer->id;
    //                     }
    //                     break;
    //                 case 2:
    //                     if (!empty($consumer->notice_no_2)) {
    //                         $existingNotices[2][] = $consumer->id;
    //                     }
    //                     break;
    //                 case 3:
    //                     if (!empty($consumer->notice_no_3)) {
    //                         $existingNotices[3][] = $consumer->id;
    //                     }
    //                     break;
    //             }
    //         }

    //         $errorMessages = [];
    //         foreach ($existingNotices as $type => $ids) {
    //             if (!empty($ids)) {
    //                 $errorMessages[] = "Notice $type has already been generated for the following consumer IDs: " . implode(', ', $ids);
    //             }
    //         }

    //         if (!empty($errorMessages)) {
    //             return responseMsgs(false, implode('; ', $errorMessages), "");
    //         }

    //         // Update generated status for consumers
    //         WaterSecondConsumer::whereIn('id', $consumerIds)
    //             ->update(['generated' => $generated]);

    //         foreach ($consumerIds as $index => $consumerId) {
    //             $consumer = WaterSecondConsumer::find($consumerId);
    //             if ($consumer) {
    //                 $idGeneration = new PrefixIdGenerator($refConParamId['AMCN'], 2);
    //                 $noticeNo = $idGeneration->generate();

    //                 switch ($noticeType) {
    //                     case 1:
    //                         $consumer->update([
    //                             'notice_no_1' => $noticeNo,
    //                             'notice' => $noticeType,
    //                             'notice_1_generated_at' => $now,
    //                         ]);

    //                         WaterTempDisconnection::create([
    //                             'consumer_id' => $consumerId,
    //                             'demand_from' => $request->demandFrom[$index],
    //                             'demand_upto' => $request->demandUpto[$index],
    //                             'amount_notice_1' => $request->amount[$index]
    //                         ]);
    //                         break;
    //                     case 2:
    //                         $consumer->update([
    //                             'notice_no_2' => $noticeNo,
    //                             'notice_2' => $noticeType,
    //                             'notice_2_generated_at' => $now
    //                         ]);
    //                         break;
    //                     case 3:
    //                         $consumer->update([
    //                             'notice_no_3' => $noticeNo,
    //                             'notice_3' => $noticeType,
    //                             'notice_3_generated_at' => $now
    //                         ]);
    //                         break;
    //                 }

    //                 $noticeNos[$consumerId] = $noticeNo;
    //             }
    //         }
    //         return responseMsgs(true, "Notices generated successfully", $noticeNos);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "");
    //     }
    // }

    public function generateNotice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'consumerId' => 'required|array',
            'consumerId.*' => 'integer',
            'generated' => 'required|boolean',
            'notice' => 'required|integer|in:1,2,3',
            'demandFrom' => 'nullable|array',
            'demandFrom.*' => 'date',
            'demandUpto' => 'nullable|array',
            'demandUpto.*' => 'date',
            'amount' => 'nullable|array',
            'amount.*' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        $noticeNos = [];
        $consumerIds = $request->consumerId;
        $noticeType = $request->notice;
        $now = Carbon::now()->format("Y-m-d");

        // Initialize arrays to track existing notices
        $existingNotices = [
            1 => [],
            2 => [],
            3 => []
        ];

        try {
            $refConParamId = Config::get('waterConstaint.PARAM_IDS');
            $generated = $request->generated;

            // Fetch consumers based on IDs
            $consumers = WaterSecondConsumer::whereIn('id', $consumerIds)->get();

            // Check for existing notices
            foreach ($consumers as $consumer) {
                switch ($noticeType) {
                    case 1:
                        if (!empty($consumer->notice_no_1)) {
                            $existingNotices[1][] = $consumer->id;
                        }
                        break;
                    case 2:
                        if (!empty($consumer->notice_no_2)) {
                            $existingNotices[2][] = $consumer->id;
                        }
                        break;
                    case 3:
                        if (!empty($consumer->notice_no_3)) {
                            $existingNotices[3][] = $consumer->id;
                        }
                        break;
                }
            }

            $errorMessages = [];
            foreach ($existingNotices as $type => $ids) {
                if (!empty($ids)) {
                    $errorMessages[] = "Notice $type has already been generated for the following consumer IDs: " . implode(', ', $ids);
                }
            }

            if (!empty($errorMessages)) {
                return responseMsgs(false, implode('; ', $errorMessages), "");
            }

            // Update generated status for consumers
            WaterSecondConsumer::whereIn('id', $consumerIds)
                ->update(['generated' => $generated]);

            foreach ($consumerIds as $index => $consumerId) {
                $consumer = WaterSecondConsumer::find($consumerId);
                if ($consumer) {
                    $idGeneration = new PrefixIdGenerator($refConParamId['AMCN'], 2);
                    $noticeNo = $idGeneration->generate();

                    switch ($noticeType) {
                        case 1:
                            $consumer->update([
                                'notice_no_1' => $noticeNo,
                                'notice' => $noticeType,
                                'notice_1_generated_at' => $now,
                            ]);

                            WaterTempDisconnection::updateOrCreate(
                                ['consumer_id' => $consumerId],
                                [
                                    'demand_from' => $request->demandFrom[$index],
                                    'demand_upto' => $request->demandUpto[$index],
                                    'amount_notice_1' => $request->amount[$index]
                                ]
                            );
                            break;
                        case 2:
                            $consumer->update([
                                'notice_no_2' => $noticeNo,
                                'notice_2' => $noticeType,
                                'notice_2_generated_at' => $now
                            ]);
                            $demandupto = $request->demandUpto[$index];
                            $totalAmount = waterConsumerDemand::where('consumer_id', $consumerId)
                                ->where('is_full_paid', false)
                                ->where('demand_upto', '<=', $demandupto)
                                ->sum('due_balance_amount');

                            WaterTempDisconnection::updateOrCreate(
                                ['consumer_id' => $consumerId],
                                [
                                    'amount_notice_2' => $totalAmount,
                                    'demand_upto_1' => $demandupto
                                ]
                            );
                            break;
                        case 3:
                            $consumer->update([
                                'notice_no_3' => $noticeNo,
                                'notice_3' => $noticeType,
                                'notice_3_generated_at' => $now
                            ]);

                            $demandupto = $request->demandUpto[$index];
                            $totalAmountNotice3 = waterConsumerDemand::where('consumer_id', $consumerId)
                                ->where('is_full_paid', false)
                                ->where('demand_upto', '<=', $demandupto)
                                ->sum('due_balance_amount');

                            WaterTempDisconnection::updateOrCreate(
                                ['consumer_id' => $consumerId],
                                [
                                    'amount_notice_3' => $totalAmountNotice3,
                                    'demand_upto_2' => $demandupto
                                ]
                            );
                            break;
                    }


                    $noticeNos[$consumerId] = $noticeNo;
                }
            }
            return responseMsgs(true, "Notices generated successfully", $noticeNos);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function generateNoticeList(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");

        $validated = Validator::make(
            $request->all(),
            [
                "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
                "notice" => "required|integer|in:1,2,3"
            ]
        );

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }

        try {
            $uptoDate = $request->uptoDate ?? $now;
            $userId = $request->userId;
            $wardId = $request->wardId;
            $zoneId = $request->zoneId;
            $notice = $request->notice;

            $data = waterConsumerDemand::select(
                "water_second_consumers.id as consumer_id",
                "water_second_consumers.consumer_no",
                "water_second_consumers.folio_no as property_no",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "water_second_consumers.category",
                "water_property_type_mstrs.property_type",
                DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount'),
                DB::raw('MIN(water_consumer_demands.demand_from) as earliest_demand_from'),
                DB::raw('MAX(water_consumer_demands.demand_upto) as latest_demand_upto'),
                //"water_second_consumers.notice",
                "water_second_consumers.notice_no_1",
                "water_second_consumers.notice_no_2",
                "water_second_consumers.notice_no_3",
            )
                ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftJoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
                ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
                ->join('water_consumer_owners as owners', 'owners.id', 'water_consumer_demands.consumer_id')
                ->where('water_consumer_demands.is_full_paid', false)
                ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
                ->where('water_second_consumers.generated', true)
                ->groupBy(
                    'water_second_consumers.id',
                    'water_second_consumers.consumer_no',
                    'water_second_consumers.folio_no',
                    'zone_masters.zone_name',
                    'ulb_ward_masters.ward_name',
                    'owners.applicant_name',
                    'owners.guardian_name',
                    'owners.mobile_no',
                    'water_second_consumers.category',
                    'water_property_type_mstrs.property_type'
                )
                ->havingRaw('SUM(water_consumer_demands.due_balance_amount) > 0');

            if ($userId) {
                $data->where('water_consumer_demands.emp_details_id', $userId);
            }
            if ($wardId) {
                $data->where('water_second_consumers.ward_mstr_id', $wardId);
            }
            if ($zoneId) {
                $data->where('water_second_consumers.zone_mstr_id', $zoneId);
            }
            if ($notice) {
                switch ($notice) {
                    case 1:
                        $data->whereNotNull('water_second_consumers.notice_no_1')
                            ->whereNull('water_second_consumers.notice_no_2')
                            ->whereNull('water_second_consumers.notice_no_3');
                        break;
                    case 2:
                        $data->whereNotNull('water_second_consumers.notice_no_2')
                            ->whereNotNull('water_second_consumers.notice_no_1')
                            ->whereNull('water_second_consumers.notice_no_3');
                        break;
                    case 3:
                        $data->whereNotNull('water_second_consumers.notice_no_3')
                            ->whereNotNull('water_second_consumers.notice_no_2')
                            ->whereNotNull('water_second_consumers.notice_no_1');
                        break;
                }
            }

            $perPage = $request->perPage ?? 10;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "Generated Notice Lists", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function generateNoticeListFinal(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");

        $validated = Validator::make(
            $request->all(),
            [
                "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
                //"notice" => "required|integer|in:1,2,3"
            ]
        );

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }

        try {
            $uptoDate = $request->uptoDate ?? $now;
            $userId = $request->userId;
            $wardId = $request->wardId;
            $zoneId = $request->zoneId;
            $notice = $request->notice;

            $data = waterConsumerDemand::select(
                "water_second_consumers.id as consumer_id",
                "water_second_consumers.consumer_no",
                "water_second_consumers.folio_no as property_no",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "water_second_consumers.category",
                "water_property_type_mstrs.property_type",
                DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount'),
                DB::raw('MIN(water_consumer_demands.demand_from) as earliest_demand_from'),
                DB::raw('MAX(water_consumer_demands.demand_upto) as latest_demand_upto'),
                "water_second_consumers.notice_3",
                "water_second_consumers.notice_no_3",
                "water_second_consumers.notice_3_generated_at",
            )
                ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftJoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
                ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
                ->join('water_consumer_owners as owners', 'owners.id', 'water_consumer_demands.consumer_id')
                ->where('water_consumer_demands.is_full_paid', false)
                ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
                ->where('water_second_consumers.generated', true)
                ->where('water_second_consumers.notice_3', 3)
                ->where('water_second_consumers.je_application', false)
                ->groupBy(
                    'water_second_consumers.id',
                    'water_second_consumers.consumer_no',
                    'water_second_consumers.folio_no',
                    'zone_masters.zone_name',
                    'ulb_ward_masters.ward_name',
                    'owners.applicant_name',
                    'owners.guardian_name',
                    'owners.mobile_no',
                    'water_second_consumers.category',
                    'water_property_type_mstrs.property_type'
                )
                ->havingRaw('SUM(water_consumer_demands.due_balance_amount) > 0');

            if ($userId) {
                $data->where('water_consumer_demands.emp_details_id', $userId);
            }
            if ($wardId) {
                $data->where('water_second_consumers.ward_mstr_id', $wardId);
            }
            if ($zoneId) {
                $data->where('water_second_consumers.zone_mstr_id', $zoneId);
            }

            $perPage = $request->perPage ?? 10;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "Generated Final Notice Lists", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function sendToJe(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");

        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => 'required|array',
                'consumerId.*' => 'integer',
                'jeApplication' => 'required|boolean'
            ]
        );

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }

        try {
            $ulbWorkflowObj = new WfWorkflow();
            //$jeSendList = new WaterTempDisconnection();
            $ulbId = 2;
            $consumerIds = $request->consumerId;
            $jeApplication = $request->jeApplication;

            // Update records in WaterSecondConsumer
            WaterSecondConsumer::whereIn('id', $consumerIds)
                ->update(['je_application' => $jeApplication, 'je_application_date' => $now]);

            // Get water details for the given consumer IDs
            $waterDtls = WaterSecondConsumer::whereIn('id', $consumerIds)
                ->get();

            $workflowID = Config::get('workflow-constants.WATER_TEMP_DEACTIVATION');
            $ulbWorkflowId = $ulbWorkflowObj->getulbWorkflowId($workflowID, $ulbId);
            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId = DB::select($refFinisherRoleId);
            $initiatorRoleId = DB::select($refInitiatorRoleId);

            foreach ($waterDtls as $waterDtl) {
                WaterTempDisconnection::updateOrCreate(
                    ['consumer_id' => $waterDtl->id],
                    [
                        'current_role' => collect($initiatorRoleId)->first()->role_id,
                        'initiator' => collect($initiatorRoleId)->first()->role_id,
                        'finisher' => collect($finisherRoleId)->first()->role_id,
                        'last_role_id' => collect($finisherRoleId)->first()->role_id,
                        'workflow_id' => $ulbWorkflowId->id,
                        'notice' => $waterDtl->notice,
                        'notice_no_1' => $waterDtl->notice_no_1,
                        'notice_no_2' => $waterDtl->notice_no_2,
                        'notice_no_3' => $waterDtl->notice_no_3,
                        'notice_2' => $waterDtl->notice_2,
                        'notice_3' => $waterDtl->notice_3,
                        'notice_1_generated_at' => $waterDtl->notice_1_generated_at,
                        'notice_2_generated_at' => $waterDtl->notice_2_generated_at,
                        'notice_3_generated_at' => $waterDtl->notice_3_generated_at,
                    ]
                );
            }

            return responseMsgs(true, "Notice List Send Successfully To JE ", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    // public function jeInbox(Request $req)
    // {
    //     $validated = Validator::make(
    //         $req->all(),
    //         [
    //             'perPage' => 'nullable|integer',
    //         ]
    //     );
    //     if ($validated->fails()) {
    //         return validationError($validated);
    //     }

    //     try {
    //         $user = authUser($req);
    //         $pages = $req->perPage ?? 10;
    //         $userId = $user->id;
    //         $ulbId = $user->ulb_id ?? 2;

    //         $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

    //         $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
    //         $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
    //         $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

    //         $inboxDtl = WaterTempDisconnection::select(
    //             "water_temp_disconnections.id as disconnection_application_id",
    //             "water_temp_disconnections.consumer_id as id",
    //             "water_second_consumers.consumer_no",
    //             "water_temp_disconnections.current_role",
    //             "water_temp_disconnections.workflow_id",
    //             "water_temp_disconnections.notice_no_3",
    //             "water_temp_disconnections.notice_3_generated_at",
    //             "water_second_consumers.holding_no",
    //             "water_second_consumers.address",
    //             "zone_masters.zone_name",
    //             "ulb_ward_masters.ward_name",
    //             "owners.applicant_name",
    //             "owners.guardian_name",
    //             "owners.mobile_no",
    //             "water_second_consumers.category",
    //             "water_property_type_mstrs.property_type",
    //             DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount'),
    //             DB::raw('MIN(water_consumer_demands.demand_from) as earliest_demand_from'),
    //             DB::raw('MAX(water_consumer_demands.demand_upto) as latest_demand_upto')
    //         )
    //             ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_temp_disconnections.consumer_id')
    //             ->join('water_consumer_demands', 'water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
    //             ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
    //             ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
    //             ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
    //             ->join('water_consumer_owners as owners', 'owners.id', '=', 'water_consumer_demands.consumer_id')
    //             ->where('water_consumer_demands.is_full_paid', false)
    //             ->where('water_second_consumers.generated', true)
    //             ->where('water_second_consumers.status', true)
    //             ->where('water_second_consumers.je_application', true)
    //             ->whereIn('water_temp_disconnections.current_role', $roleId)
    //             ->whereIn('water_temp_disconnections.workflow_id', $workflowIds)
    //             ->where('water_temp_disconnections.status', 1)
    //             ->groupBy(
    //                 'water_temp_disconnections.id',
    //                 'water_temp_disconnections.consumer_id',
    //                 'water_temp_disconnections.current_role',
    //                 'water_temp_disconnections.workflow_id',
    //                 'water_temp_disconnections.notice_no_3',
    //                 'water_temp_disconnections.notice_3_generated_at',
    //                 'zone_masters.zone_name',
    //                 'ulb_ward_masters.ward_name',
    //                 'owners.applicant_name',
    //                 'owners.guardian_name',
    //                 'owners.mobile_no',
    //                 'water_second_consumers.category',
    //                 'water_property_type_mstrs.property_type',
    //                 'water_second_consumers.consumer_no',
    //                 "water_second_consumers.holding_no",
    //                 "water_second_consumers.address"
    //             )
    //             ->havingRaw('SUM(water_consumer_demands.due_balance_amount) > 0')
    //             ->get();

    //         return responseMsgs(true, "Notice Generated Details", remove_null($inboxDtl), "", "01", responseTime(), "POST", $req->deviceId);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), "POST", $req->deviceId);
    //     }
    // }

    public function jeInbox(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'perPage' => 'nullable|integer',
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $user = authUser($req);
            $pages = $req->perPage ?? 10;
            $userId = $user->id;
            $ulbId = $user->ulb_id ?? 2;

            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleIds = $this->getRoleIdByUserId($userId)->pluck('wf_role_id')->toArray();
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $inboxDtlQuery = WaterTempDisconnection::select(
                "water_temp_disconnections.id as disconnection_application_id",
                "water_temp_disconnections.consumer_id as id",
                "water_second_consumers.consumer_no",
                "water_temp_disconnections.current_role",
                "water_temp_disconnections.workflow_id",
                "water_temp_disconnections.notice_no_3",
                "water_temp_disconnections.notice_3_generated_at",
                "water_second_consumers.holding_no",
                "water_second_consumers.address",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "water_second_consumers.category",
                "water_property_type_mstrs.property_type",
                "water_temp_disconnections.demand_from as earliest_demand_from",
                "water_temp_disconnections.demand_upto as latest_demand_upto",
                DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount')
            )
                ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_temp_disconnections.consumer_id')
                ->join('water_consumer_demands', function ($join) {
                    $join->on('water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
                        ->whereColumn('water_consumer_demands.demand_upto', '<=', 'water_temp_disconnections.demand_upto');
                })
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
                ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
                ->join('water_consumer_owners as owners', 'owners.id', '=', 'water_second_consumers.id')
                ->where('water_second_consumers.generated', true)
                ->where('water_second_consumers.status', true)
                ->where('water_second_consumers.je_application', true)
                ->whereIn('water_temp_disconnections.workflow_id', $workflowIds)
                ->where('water_temp_disconnections.status', 1)
                ->groupBy(
                    'water_temp_disconnections.id',
                    'water_temp_disconnections.consumer_id',
                    'water_temp_disconnections.current_role',
                    'water_temp_disconnections.workflow_id',
                    'water_temp_disconnections.notice_no_3',
                    'water_temp_disconnections.notice_3_generated_at',
                    'zone_masters.zone_name',
                    'ulb_ward_masters.ward_name',
                    'owners.applicant_name',
                    'owners.guardian_name',
                    'owners.mobile_no',
                    'water_second_consumers.category',
                    'water_property_type_mstrs.property_type',
                    'water_second_consumers.consumer_no',
                    "water_second_consumers.holding_no",
                    "water_second_consumers.address",
                    "water_temp_disconnections.demand_from",
                    "water_temp_disconnections.demand_upto"
                );

            if (in_array(12, $roleIds)) {
                $inboxDtl = $inboxDtlQuery->whereIn('water_temp_disconnections.current_role', [12])
                    ->havingRaw('SUM(water_consumer_demands.due_balance_amount) > 0')
                    ->get();
            } elseif (in_array(14, $roleIds)) {
                $inboxDtl = $inboxDtlQuery->whereIn('water_temp_disconnections.current_role', [14])
                    ->havingRaw('SUM(water_consumer_demands.due_balance_amount) >= 0')
                    ->get();
            } elseif (in_array(10, $roleIds)) {
                $inboxDtl = $inboxDtlQuery->whereIn('water_temp_disconnections.current_role', [10])
                    ->havingRaw('SUM(water_consumer_demands.due_balance_amount) >= 0')
                    ->get();
            } else {
                $inboxDtl = collect(); // Return an empty collection if none of the roles match
            }

            return responseMsgs(true, "Notice Generated Details", remove_null($inboxDtl), "", "01", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), "POST", $req->deviceId);
        }
    }





    public function jeOutbox(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'perPage' => 'nullable|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user                   = authUser($req);
            $pages                  = $req->perPage ?? 10;
            $userId                 = $user->id;
            $ulbId                  = $user->ulb_id;
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();

            $workflowRoles = $this->getRoleIdByUserId($userId);
            $roleId = $workflowRoles->map(function ($value) {                         // Get user Workflow Roles
                return $value->wf_role_id;
            });
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');
            $outDtl = WaterTempDisconnection::select(
                "water_temp_disconnections.id as disconnection_application_id",
                "water_temp_disconnections.consumer_id as id",
                "water_second_consumers.consumer_no",
                "water_temp_disconnections.current_role",
                "water_temp_disconnections.workflow_id",
                "water_temp_disconnections.notice_no_3",
                "water_temp_disconnections.notice_3_generated_at",
                "water_second_consumers.holding_no",
                "water_second_consumers.address",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "water_second_consumers.category",
                "water_property_type_mstrs.property_type",
                DB::raw('SUM(water_consumer_demands.due_balance_amount) as total_amount'),
                DB::raw('MIN(water_consumer_demands.demand_from) as earliest_demand_from'),
                DB::raw('MAX(water_consumer_demands.demand_upto) as latest_demand_upto')
            )
                ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_temp_disconnections.consumer_id')
                ->join('water_consumer_demands', 'water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
                ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
                ->join('water_consumer_owners as owners', 'owners.id', '=', 'water_consumer_demands.consumer_id')
                ->where('water_consumer_demands.is_full_paid', false)
                ->where('water_second_consumers.generated', true)
                ->where('water_second_consumers.status', true)
                ->where('water_second_consumers.je_application', true)
                ->whereNotIn('water_temp_disconnections.current_role', $roleId)
                ->whereIn('water_temp_disconnections.workflow_id', $workflowIds)
                ->where('water_temp_disconnections.status', 1)
                ->groupBy(
                    'water_temp_disconnections.id',
                    'water_temp_disconnections.consumer_id',
                    'water_temp_disconnections.current_role',
                    'water_temp_disconnections.workflow_id',
                    'water_temp_disconnections.notice_no_3',
                    'water_temp_disconnections.notice_3_generated_at',
                    'zone_masters.zone_name',
                    'ulb_ward_masters.ward_name',
                    'owners.applicant_name',
                    'owners.guardian_name',
                    'owners.mobile_no',
                    'water_second_consumers.category',
                    'water_property_type_mstrs.property_type',
                    'water_second_consumers.consumer_no',
                    "water_second_consumers.holding_no",
                    "water_second_consumers.address"
                )
                ->havingRaw('SUM(water_consumer_demands.due_balance_amount) > 0')
                ->get();


            return responseMsgs(true, "Je outbox Details", remove_null($outDtl), "", "01", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), "POST", $req->deviceId);
        }
    }

    public function viewDetail(Request $request)
    {

        $request->validate([
            'applicationId' => "required"

        ]);

        try {
            return $this->getApplicationsDetailsv1($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function getApplicationsDetailsv1($request)
    {

        $forwardBackward        = new WorkflowMap();
        $mWorkflowTracks        = new WorkflowTrack();
        $mCustomDetails         = new CustomDetail();
        $mUlbNewWardmap         = new UlbWardMaster();
        $mwaterConsumerActive   = new WaterSecondConsumer();
        $mwaterOwner            = new WaterConsumerOwner();
        # applicatin details
        $applicationDetails = $mwaterConsumerActive->fullWaterDetailsV3($request)->get();

        if (collect($applicationDetails)->first() == null) {
            return responseMsg(false, "Application Data Not found!", $request->applicationId);
        }
        //$consumerId = $applicationDetails->pluck('consumer_id');
        $consumerId = $request->applicationId;
        # Ward Name
        $refApplication = collect($applicationDetails)->first();
        // $wardDetails = $mUlbNewWardmap->getWard($refApplication->ward_mstr_id);
        # owner Details
        $ownerDetails = $mwaterOwner->getConsumerOwner($consumerId)->get();
        $ownerDetail = collect($ownerDetails)->map(function ($value, $key) {
            return $value;
        });
        $aplictionList = [
            'notice_no' => collect($applicationDetails)->first()->notice_no_3,
            'notice_date' => collect($applicationDetails)->first()->notice_3_generated_at,
            //'charge_catagory_id' => $applicationDetails->pluck('charge_catagory_id')->first()
        ];


        # DataArray
        $basicDetails = $this->getBasicDetails($applicationDetails);

        $firstView = [
            'headerTitle' => 'Basic Details',
            'data' => $basicDetails
        ];
        $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$firstView]);
        # CardArray
        $cardDetails = $this->getCardDetails($applicationDetails, $ownerDetail);
        $chargeCatgory =  $applicationDetails->pluck('charge_category');
        //$chargeCatgoryId = $applicationDetails->pluck('charge_catagory_id')->first();
        $cardData = [
            'headerTitle' => $chargeCatgory,
            'data' => $cardDetails
        ];
        $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardData);
        # TableArray
        $ownerView = [];
        //if ($chargeCatgoryId != 10) {
        $ownerList = $this->getOwnerDetails($ownerDetail);
        $ownerView = [
            'headerTitle' => 'Owner Details',
            'tableHead' => ["#", "Owner Name", "Guardian Name", "Mobile No", "Email", "City", "District"],
            'tableData' => $ownerList
        ];
        // }

        $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerView]);

        # Level comment
        $mtableId = $applicationDetails->first()->id;
        $mRefTable = "water_second_consumers.id";
        $levelComment['levelComment'] = $mWorkflowTracks->getTracksByRefId($mRefTable, $mtableId);

        #citizen comment
        $refCitizenId = $applicationDetails->first()->citizen_id;
        $citizenComment['citizenComment'] = $mWorkflowTracks->getCitizenTracks($mRefTable, $mtableId, $refCitizenId);

        # Role Details
        $data = json_decode(json_encode($applicationDetails->first()), true);
        $metaReqs = [
            'customFor' => 'Water Deactivation',
            'wfRoleId' => $data['current_role'],
            'workflowId' => $data['workflow_id'],
            'lastRoleId' => $data['last_role_id']
        ];
        $request->request->add($metaReqs);
        $forwardBackward = $forwardBackward->getRoleDetails($request);
        $roleDetails['roleDetails'] = collect($forwardBackward)['original']['data'];

        # Timeline Data
        $timelineData['timelineData'] = collect($request);

        # Departmental Post
        $custom = $mCustomDetails->getCustomDetails($request);
        $departmentPost['departmentalPost'] = collect($custom)['original']['data'];
        # Payments Details
        $returnValues = array_merge($aplictionList, $fullDetailsData, $levelComment, $citizenComment, $roleDetails, $timelineData, $departmentPost);
        return responseMsgs(true, "listed Data!", remove_null($returnValues), "", "02", ".ms", "POST", "");
    }
    /**
     * Function for returning basic details data
     */
    public function getBasicDetails($applicationDetails)
    {
        $collectionApplications = collect($applicationDetails)->first();
        $basicDetails = [];

        // Common basic details
        $commonDetails = [
            ['displayString' => 'Ward No', 'key' => 'WardNo', 'value' => $collectionApplications->ward_name],
            ['displayString' => 'Zone', 'key' => 'Zone', 'value' => $collectionApplications->zone_name],
            ['displayString' => 'Property No', 'key' => 'PropertyNo', 'value' => $collectionApplications->property_no],
            ['displayString' => 'Connection Type', 'key' => 'ConnectionType', 'value' => $collectionApplications->connection_type],
            ['displayString' => 'Property Type', 'key' => 'PropertyType', 'value' => $collectionApplications->property_type],
            ['displayString' => 'Category', 'key' => 'Category', 'value' => $collectionApplications->category],
            ['displayString' => 'Address', 'key' => 'Address', 'value' => $collectionApplications->address],
            ['displayString' => 'Road Width', 'key' => 'RoadWidth', 'value' => $collectionApplications->per_meter],
            ['displayString' => 'Mobile Number', 'key' => 'MobileNumber', 'value' => $collectionApplications->basicmobile],
            ['displayString' => 'Road Type', 'key' => 'RoadType', 'value' => $collectionApplications->road_type],
            ['displayString' => 'Initial Reading', 'key' => 'InitialReading', 'value' => $collectionApplications->initial_reading],
            ['displayString' => 'Land Mark', 'key' => 'LandMark', 'value' => $collectionApplications->land_mark],
            ['displayString' => 'Tap Size', 'key' => 'tapSize', 'value' => $collectionApplications->tab_size]
        ];
        $basicDetails = array_merge($commonDetails);
        return collect($basicDetails);
    }


    /**
     * return data fro card details 
     */
    public function getCardDetails($applicationDetails, $ownerDetail)
    {
        $ownerName = collect($ownerDetail)->map(function ($value) {
            return $value['owner_name'];
        });
        $ownerDetail = $ownerName->implode(',');
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([
            ['displayString' => 'Notice1', 'key' => 'noticeNumber1', 'value' => $collectionApplications->notice_no_1],
            ['displayString' => 'Notice2', 'key' => 'noticeNumber2', 'value' => $collectionApplications->notice_no_2],
            ['displayString' => 'Notice3', 'key' => 'noticeNumber3', 'value' => $collectionApplications->notice_no_3],
            ['displayString' => 'Notice Date1', 'key' => 'noticeDate1', 'value' => $collectionApplications->notice_1_generated_at],
            ['displayString' => 'Notice Date2', 'key' => 'noticeDate2', 'value' => $collectionApplications->notice_2_generated_at],
            ['displayString' => 'Notice Date3', 'key' => 'noticeDate3', 'value' => $collectionApplications->notice_3_generated_at],
            ['displayString' => 'Consumer No ',         'key' => 'Consumer',           'value' => $collectionApplications->consumer_no],
        ]);
    }
    public function getOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($value, $key) {
            return [
                $key + 1,
                $value['applicant_name'],
                $value['guardian_name'],
                $value['mobile_no'],
                $value['email'],
                $value['city'],
                $value['district']
            ];
        });
    }

    public function DocToUpload(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterApplication  = new WaterTempDisconnection();
            $refWaterApplication = $mWaterApplication->getActiveReqById($req->applicationId)->first();
            if (!$refWaterApplication) {
                throw new Exception("Application Not Found for this id");
            }
            $documentList = $this->getWaterDocLists($refWaterApplication, $req);
            $waterTypeDocs['listDocs'] = collect($documentList)->map(function ($value, $key) use ($refWaterApplication) {
                return $this->filterDocument($value, $refWaterApplication)->first();
            });

            $totalDocLists = collect($waterTypeDocs); //->merge($waterOwnerDocs);
            $totalDocLists['docUploadStatus'] = $refWaterApplication->doc_upload_status;
            $totalDocLists['docVerifyStatus'] = $refWaterApplication->doc_status;
            return responseMsgs(true, "", remove_null($totalDocLists), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    public function getWaterDocLists($application, $req)
    {
        $mRefReqDocs    = new RefRequiredDocument();
        $moduleId       = Config::get('module-constants.WATER_MODULE_ID') ?? 2;
        $type = ["SITE REPORT"];
        //$type = "SITE REPORT";
        return $mRefReqDocs->getCollectiveDocByCode($moduleId, $type);
    }

    public function filterDocument($documentList, $refWaterApplication, $ownerId = null)
    {
        $mWfActiveDocument  = new WfActiveDocument();
        $applicationId      = $refWaterApplication->consumer_id;
        $workflowId         = $refWaterApplication->workflow_id;
        $moduleId           = Config::get('module-constants.WATER_MODULE_ID');
        $uploadedDocs       = $mWfActiveDocument->getDocByRefIdsV4($applicationId, $workflowId, $moduleId);

        $explodeDocs = collect(explode('#', $documentList->requirements));
        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId, $documentList) {

            # var defining
            $document   = explode(',', $explodeDoc);
            $key        = array_shift($document);
            $label      = array_shift($document);
            $documents  = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId, $documentList) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if ($uploadedDoc) {
                    $path = $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode"  => $item,
                        "ownerId"       => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath"       => $fullDocPath ?? "",
                        "verifyStatus"  => $uploadedDoc->verify_status ?? "",
                        "remarks"       => $uploadedDoc->remarks ?? "",
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType']      = $key;
            $reqDoc['uploadedDoc']  = $documents->last();
            $reqDoc['docName']      = substr($label, 1, -1);
            // $reqDoc['refDocName'] = substr($label, 1, -1);

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                if (isset($uploadedDoc)) {
                    $path =  $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                }
                $arr = [
                    "documentCode"  => $doc,
                    "docVal"        => ucwords($strReplace),
                    "uploadedDoc"   => $fullDocPath ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks"       => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return $filteredDocs;
    }

    public function readDocumentPath($path)
    {
        $path = (config('app.url') . "/" . $path);
        return $path;
    }
    public function DocUpload(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "applicationId" => "required|numeric",
                "document"      => "required|mimes:pdf,jpeg,png,jpg|max:2048",
                "docCode"       => "required",
                "docCategory"   => "required",                                  // Recheck in case of undefined
                "ownerId"       => "nullable|numeric"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user               = authUser($req);
            $metaReqs           = array();
            $applicationId      = $req->applicationId;
            $document           = $req->document;
            $docUpload          = new DocUpload;
            $mWfActiveDocument  = new WfActiveDocument();
            $mWaterApplication  = new WaterTempDisconnection();
            $relativePath       = Config::get('waterConstaint.WATER_RELATIVE_PATH');
            $refmoduleId        = Config::get('module-constants.WATER_MODULE_ID');

            $getWaterDetails    = $mWaterApplication->fullDetails($req)->firstOrFail();
            if ($getWaterDetails->doc_upload_status === true) {
                throw new Exception("Document has uploaded");
            }

            $refImageName       = $req->docRefName;
            $refImageName       = $getWaterDetails->id . '-' . str_replace(' ', '_', $refImageName);
            $imageName          = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs = [
                'moduleId'      => $refmoduleId,
                'activeId'      => $getWaterDetails->consumer_id,
                'workflowId'    => $getWaterDetails->workflow_id,
                'ulbId'         => $getWaterDetails->ulb_id ?? 2,
                'relativePath'  => $relativePath,
                'document'      => $imageName,
                'docCode'       => $req->docCode,
                'ownerDtlId'    => $req->ownerId,
                'docCategory'   => $req->docCategory,
                'auth'          => $req->auth
            ];

            DB::beginTransaction();
            $ifDocExist = $mWfActiveDocument->isDocCategoryExists($getWaterDetails->id, $getWaterDetails->workflow_id, $refmoduleId, $req->docCategory, $req->ownerId)->first();   // Checking if the document is already existing or not
            $metaReqs = new Request($metaReqs);
            if (collect($ifDocExist)->isEmpty()) {
                $mWfActiveDocument->postDocuments($metaReqs);
            }
            if (collect($ifDocExist)->isNotEmpty()) {
                $mWfActiveDocument->editDocuments($ifDocExist, $metaReqs);
            }
            WaterTempDisconnection::where('consumer_id', $req->applicationId)
                ->update(['doc_upload_status' => true]);
            DB::commit();
            return responseMsgs(true, "Document Uploadation Successful", "", "", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function consumerPostNextLevel(Request $request)
    {
        $wfLevels = Config::get('waterConstaint.ROLE-LABEL');
        $request->validate([
            'applicationId'     => 'required',
            'senderRoleId'      => 'required',
            'receiverRoleId'    => 'required',
            'action'            => 'required|In:forward',
            'comment'           => 'required',

        ]);
        try {
            return $this->postNextLevelRequest($request);
        } catch (Exception $error) {
            DB::rollBack();
            return responseMsg(false, $error->getMessage(), "");
        }
    }
    public function postNextLevelRequest($req)
    {

        $mWfWorkflows        = new WfWorkflow();
        $mWfRoleMaps         = new WfWorkflowrolemap();

        $current             = Carbon::now();
        $wfLevels            = Config::get('waterConstaint.ROLE-LABEL');
        //$waterConsumerActive = WaterTempDisconnection::find('consumer_id', $req->applicationId);
        $waterConsumerActive = WaterTempDisconnection::where('consumer_id', $req->applicationId)->firstOrFail();

        # Derivative Assignments
        $senderRoleId   = $waterConsumerActive->current_role;
        $ulbWorkflowId  = $waterConsumerActive->workflow_id;
        $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
        $roleMapsReqs   = new Request([
            'workflowId' => $ulbWorkflowMaps->id,
            'roleId' => $senderRoleId
        ]);
        $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);

        DB::beginTransaction();
        if ($req->action == 'forward') {
            $this->checkPostCondition($req->senderRoleId, $wfLevels, $waterConsumerActive);
            $waterConsumerActive->current_role = $forwardBackwardIds->forward_role_id;
            //$waterConsumerActive->last_role_id =  $forwardBackwardIds->forward_role_id;
        }
        $waterConsumerActive->save();
        $metaReqs['moduleId']           =  2;
        $metaReqs['workflowId']         = $waterConsumerActive->workflow_id;
        $metaReqs['refTableDotId']      = 'water_temp_disconnections.consumer_id';
        $metaReqs['refTableIdValue']    = $req->applicationId;
        $metaReqs['user_id']            = authUser($req)->id;
        $req->request->add($metaReqs);
        $waterTrack         = new WorkflowTrack();
        $waterTrack->saveTrack($req);

        # check in all the cases the data if entered in the track table 
        // Updation of Received Date
        $preWorkflowReq = [
            'workflowId'        => $waterConsumerActive->workflow_id,
            'refTableDotId'     => "water_temp_disconnections.consumer_id",
            'refTableIdValue'   => $req->applicationId,
            'receiverRoleId'    => $senderRoleId
        ];

        $previousWorkflowTrack = $waterTrack->getWfTrackByRefId($preWorkflowReq);
        if ($previousWorkflowTrack) {
            $previousWorkflowTrack->update([
                'forward_date' => $current,
                'forward_time' => $current
            ]);
        }
        DB::commit();
        return responseMsgs(true, "Successfully Forwarded The Application!!", "", "", "", '01', '.ms', 'Post', '');
    }

    public function checkPostCondition($senderRoleId, $wfLevels, $application)
    {
        switch ($senderRoleId) {
            case $wfLevels['JE']:
                if (!$application->doc_upload_status) {
                    throw new Exception("Please upload Document");
                }
                break;
        }
    }

    public function getDiscUploadDocuments(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWfActiveDocument    = new WfActiveDocument();
            $mWaterActiveRequestApplication = new WaterTempDisconnection();
            $moduleId = Config::get('module-constants.WATER_MODULE_ID');

            $waterDetails = $mWaterActiveRequestApplication->getActiveReqById($req->applicationId)->first();
            if (!$waterDetails)
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $waterDetails->workflow_id;
            $documents = $mWfActiveDocument->getWaterDocsByAppNo($req->applicationId, $workflowId, $moduleId);
            $returnData = collect($documents)->map(function ($value) {                          // Static
                $path =  $this->readDocumentPath($value->ref_doc_path);
                $value->doc_path = !empty(trim($value->ref_doc_path)) ? $path : null;
                return $value;
            });
            return responseMsgs(true, "Uploaded Documents", remove_null($returnData), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function consumerApprovalRejection(Request $request)
    {
        $request->validate([
            "applicationId" => "required",
            "status"        => "required",
            "comment"       => "required"
        ]);
        try {
            $mWfRoleUsermap = new WfRoleusermap();
            $waterDetails = WaterTempDisconnection::where('consumer_id', $request->applicationId)->firstOrFail();

            # check the login user is AE or not
            $userId = authUser($request)->id;
            $workflowId = $waterDetails->workflow_id;
            $getRoleReq = new Request([
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            $roleId = $readRoleDtls->wf_role_id;
            if ($roleId != $waterDetails->finisher) {
                throw new Exception("You are not the Finisher!");
            }
            DB::beginTransaction();
            WaterTempDisconnection::where('consumer_id', $request->applicationId)
                ->update(['status' => 0]);
            DB::commit();
            return responseMsg(true, "Request approved successfully", "");;
        } catch (Exception $e) {
            DB::rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function consumerNotice(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => 'required|integer',

            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $consumerId = $request->consumerId;
            $query = WaterSecondConsumer::select(
                'water_second_consumers.id',
                'water_second_consumers.consumer_no',
                'water_second_consumers.ward_mstr_id',
                'water_second_consumers.address',
                'water_second_consumers.holding_no',
                'water_second_consumers.saf_no',
                'water_second_consumers.ulb_id',
                'ulb_ward_masters.ward_name',
                "water_temp_disconnections.status as deactivated_status",
                "water_temp_disconnections.demand_upto",
                "water_temp_disconnections.amount_notice_1",
                "water_temp_disconnections.amount_notice_2",
                "water_temp_disconnections.amount_notice_3",
                "water_second_consumers.notice_no_1",
                "water_second_consumers.notice_no_2",
                "water_second_consumers.notice_no_3",
                "water_second_consumers.notice",
                "water_second_consumers.notice_2",
                "water_second_consumers.notice_3",
                "water_second_consumers.notice_1_generated_at",
                "water_second_consumers.notice_2_generated_at",
                "water_second_consumers.notice_3_generated_at",
                DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
                DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
                DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name"),
                // DB::raw('SUM(water_consumer_demands.due_balance_amount) as due_balance_amount')

            )
                ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
                ->leftjoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
                // ->join('water_consumer_demands', function ($join) {
                //     $join->on('water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
                //         ->whereColumn('water_consumer_demands.demand_upto', '<=', 'water_temp_disconnections.demand_upto');
                // })
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->where('water_second_consumers.id', $consumerId)
                ->where('water_second_consumers.status', 1)
                ->groupby('water_second_consumers.id', "ulb_ward_masters.ward_name", "water_temp_disconnections.status", "water_temp_disconnections.demand_upto", "water_temp_disconnections.amount_notice_1", "water_temp_disconnections.amount_notice_2", "water_temp_disconnections.amount_notice_3");

            $notice1Data = $query->clone()->whereNotNull('water_second_consumers.notice_no_1')->first();
            $notice2Data = $query->clone()->whereNotNull('water_second_consumers.notice_no_2')->first();
            $notice3Data = $query->clone()->whereNotNull('water_second_consumers.notice_no_3')->first();

            $response = [
                'notice1' => $notice1Data,
                'notice2' =>   $notice2Data,
                'notice3' => $notice3Data
            ];

            return responseMsgs(true, "Generated Notice Lists", $response);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    public function bulkNotice1(Request $request)
    {
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }

            $query = WaterSecondConsumer::select(
                'water_second_consumers.id',
                'water_second_consumers.consumer_no',
                'water_second_consumers.ward_mstr_id',
                'water_second_consumers.address',
                'water_second_consumers.holding_no',
                'water_second_consumers.saf_no',
                'water_second_consumers.ulb_id',
                'ulb_ward_masters.ward_name',
                "water_temp_disconnections.status as deactivated_status",
                "water_temp_disconnections.demand_upto",
                "water_temp_disconnections.amount_notice_1",
                "water_second_consumers.notice_no_1",
                "water_second_consumers.notice_1_generated_at",
                DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
                DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
                DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name")
            )
                ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
                ->leftjoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->where('water_second_consumers.status', 1)
                ->groupby('water_second_consumers.id', "ulb_ward_masters.ward_name", "water_temp_disconnections.status", "water_temp_disconnections.demand_upto", "water_temp_disconnections.amount_notice_1");

            // Apply filters
            if ($request->wardId) {
                $query->where('water_second_consumers.ward_mstr_id', $request->wardId);
            }
            if ($fromDate && $uptoDate) {
                $query->whereBetween('water_second_consumers.notice_1_generated_at', [$fromDate, $uptoDate]);
            }

            // if ($request->zoneId) {
            //     $query->where('ulb_ward_masters.zone_id', $request->zoneId); // Assuming zone_id is in ulb_ward_masters
            // 
            $perPage = $request->perPage ?: 200;
            $paginatedData = $query->paginate($perPage);

            $response = [
                'current_page' => $paginatedData->currentPage(),
                'data' => $paginatedData->items(),
                'total' => $paginatedData->total(),
                'per_page' => $paginatedData->perPage(),
                'last_page' => $paginatedData->lastPage()
            ];

            return responseMsgs(true, 'Bulk Notice One', $response, '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function bulkNotice2(Request $request)
    {
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }

            $query = WaterSecondConsumer::select(
                'water_second_consumers.id',
                'water_second_consumers.consumer_no',
                'water_second_consumers.ward_mstr_id',
                'water_second_consumers.address',
                'water_second_consumers.holding_no',
                'water_second_consumers.saf_no',
                'water_second_consumers.ulb_id',
                'ulb_ward_masters.ward_name',
                "water_temp_disconnections.status as deactivated_status",
                "water_temp_disconnections.demand_upto_1",
                "water_temp_disconnections.amount_notice_2",
                "water_second_consumers.notice_no_2",
                "water_second_consumers.notice_2_generated_at",
                DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
                DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
                DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name")
            )
                ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
                ->leftjoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->where('water_second_consumers.status', 1)
                ->groupby('water_second_consumers.id', "ulb_ward_masters.ward_name", "water_temp_disconnections.status", "water_temp_disconnections.demand_upto_1", "water_temp_disconnections.amount_notice_2");

            // Apply filters
            if ($request->wardId) {
                $query->where('water_second_consumers.ward_mstr_id', $request->wardId);
            }
            if ($fromDate && $uptoDate) {
                $query->whereBetween('water_second_consumers.notice_2_generated_at', [$fromDate, $uptoDate]);
            }

            // if ($request->zoneId) {
            //     $query->where('ulb_ward_masters.zone_id', $request->zoneId); // Assuming zone_id is in ulb_ward_masters
            // 
            $perPage = $request->perPage ?: 200;
            $paginatedData = $query->paginate($perPage);

            $response = [
                'current_page' => $paginatedData->currentPage(),
                'data' => $paginatedData->items(),
                'total' => $paginatedData->total(),
                'per_page' => $paginatedData->perPage(),
                'last_page' => $paginatedData->lastPage()
            ];

            return responseMsgs(true, 'Bulk Notice Two', $response, '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function bulkNotice3(Request $request)
    {
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }

            $query = WaterSecondConsumer::select(
                'water_second_consumers.id',
                'water_second_consumers.consumer_no',
                'water_second_consumers.ward_mstr_id',
                'water_second_consumers.address',
                'water_second_consumers.holding_no',
                'water_second_consumers.saf_no',
                'water_second_consumers.ulb_id',
                'ulb_ward_masters.ward_name',
                "water_temp_disconnections.status as deactivated_status",
                "water_temp_disconnections.demand_upto_2",
                "water_temp_disconnections.amount_notice_3",
                "water_second_consumers.notice_no_3",
                "water_second_consumers.notice_3_generated_at",
                DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
                DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
                DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name")
            )
                ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
                ->leftjoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
                ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->where('water_second_consumers.status', 1)
                ->groupby('water_second_consumers.id', "ulb_ward_masters.ward_name", "water_temp_disconnections.status", "water_temp_disconnections.demand_upto_2", "water_temp_disconnections.amount_notice_3");

            // Apply filters
            if ($request->wardId) {
                $query->where('water_second_consumers.ward_mstr_id', $request->wardId);
            }
            if ($fromDate && $uptoDate) {
                $query->whereBetween('water_second_consumers.notice_3_generated_at', [$fromDate, $uptoDate]);
            }

            // if ($request->zoneId) {
            //     $query->where('ulb_ward_masters.zone_id', $request->zoneId); // Assuming zone_id is in ulb_ward_masters
            // 
            $perPage = $request->perPage ?: 200;
            $paginatedData = $query->paginate($perPage);

            $response = [
                'current_page' => $paginatedData->currentPage(),
                'data' => $paginatedData->items(),
                'total' => $paginatedData->total(),
                'per_page' => $paginatedData->perPage(),
                'last_page' => $paginatedData->lastPage()
            ];

            return responseMsgs(true, 'Bulk Notice Three', $response, '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * 
     *|Report of Demand correction 
     */
    public function searchUpdateConsumerDemand(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"    => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);
        try {
            $fromDate = $uptoDate = $now;
            $userId = $wardId = $zoneId = null;
            $key = $request->key;
            if ($key) {
                $fromDate = $uptoDate = null;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $data = WaterConsumerDemandRecord::select(
                "water_consumer_demand_records.id",
                "water_consumer_demand_records.consumer_id",
                "water_second_consumers.consumer_no",
                "water_second_consumers.address",
                "water_consumer_demand_records.created_at AS created_at",
                "water_second_consumers.folio_no as property_no",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "users.name AS user_name",
            )
                ->leftjoin('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demand_records.consumer_id')
                ->leftJoin("users", "users.id", "water_consumer_demand_records.emp_details_id")
                ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
                ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
                ->leftJoin('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_consumer_demand_records.consumer_id')

                ->leftJoin(DB::raw("(
                        SELECT water_consumer_owners.consumer_id,
                            string_agg(water_consumer_owners.applicant_name,',') as applicant_name,
                            string_agg(water_consumer_owners.guardian_name,',') as guardian_name,
                            string_agg(water_consumer_owners.mobile_no,',') as mobile_no
                        FROM water_consumer_owners
                        JOIN water_consumer_demand_records ON water_consumer_demand_records.consumer_id = water_consumer_owners.consumer_id
                        WHERE CAST(water_consumer_demand_records.created_at AS DATE) BETWEEN '$fromDate' AND '$uptoDate' 
                        " . ($userId ? " AND water_consumer_demand_records.emp_details_id = $userId" : "") . "
                        " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? " AND water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                        GROUP BY water_consumer_owners.consumer_id
                    )owners"), "owners.consumer_id", "water_consumer_demand_records.consumer_id");
            if ($fromDate && $uptoDate) {
                $data->whereBetween(DB::raw("CAST(water_consumer_demand_records.created_at AS DATE)"), [$fromDate, $uptoDate]);
            }
            if ($userId) {
                $data->where("water_consumer_demand_records.emp_details_id", $userId);
            }
            if ($wardId) {
                $data->where("water_second_consumers.ward_mstr_id", $wardId);
            }
            if ($zoneId) {
                $data->where("water_second_consumers.zone_mstr_id", $zoneId);
            }
            if ($key) {
                $data->where(function ($where) use ($key) {
                    $where->orWhere("water_second_consumers.consumer_no", "ILIKE", "%$key%")
                        // ->orWhere("water_consumers_updating_logs.old_consumer_no", "ILIKE", "%$key%")
                        // ->orWhere("water_consumers_updating_logs.address", "ILIKE", "%$key%")
                        ->orWhere("owners.applicant_name", "ILIKE", "%$key%")
                        ->orWhere("owners.guardian_name", "ILIKE", "%$key%")
                        ->orWhere("owners.mobile_no", "ILIKE", "%$key%");
                });
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }
    public function consumerUpdateDemandLogs(Request $request)
    {
        $logs = new  WaterConsumerDemandRecord();
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'         => "required|integer|exists:" . $logs->getConnectionName() . "." . $logs->getTable() . ",id",
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);
        try {
            $newConsumerData = new WaterSecondConsumer();
            $mNewConsumerDemand = new WaterConsumerDemand();
            $consumerLog = $logs->find($request->applicationId);
            $users = User::find($consumerLog->emp_details_id);
            $consumerLog->property_type = $consumerLog->getProperty()->property_type ?? "";
            $consumerLog->zone = ZoneMaster::find($consumerLog->zone_mstr_id)->zone_name ?? "";
            $consumerLog->ward_name = UlbWardMaster::find($consumerLog->ward_mstr_id)->ward_name ?? "";
            $ownres      = $consumerLog->getOwners();
            # updated details 
            $getNewData     = $mNewConsumerDemand->consumerDemandByConsumerIds($consumerLog->consumer_id);

            $commonFunction = new \App\Repository\Common\CommonFunction();
            $rols = ($commonFunction->getUserAllRoles($users->id)->first());
            $docUrl = Config::get('module-constants.DOC_URL');
            $header = [
                "user_name" => $users->name ?? "",
                "role" => $rols->role_name ?? "",
                "remarks" => $consumerLog->remarks ?? "",
                "upate_date" => $consumerLog->up_created_at ? Carbon::parse($consumerLog->up_created_at)->format("d-m-Y h:i:s A") : "",
                "document" => $consumerLog->document ? trim($docUrl . "/" . $consumerLog->relative_path . "/" . $consumerLog->document, "/") : "",
            ];
            $data = [
                "userDtls" => $header,
                "oldConsumer" => $consumerLog,
                "newConsumer" => $getNewData,
                "oldOwnere" => $ownres,
                // "newOwnere" => $newOwnresData,
            ];
            return responseMsgs(true, "Log Details", remove_null($data), "", "010203", "1.0", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }
    /**
     * | water Collection report For New Connection 
     */
    public function tcNewCollectionReport(colllectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        // return $request->all();
        try {

            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = null;
            $now                        = Carbon::now()->format('Y-m-d');
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $fromDate = $uptoDate       = Carbon::now()->format("Y-m-d");
            $now                        = Carbon::now();
            $currentDate                = Carbon::now()->format('Y-m-d');
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $currentDate                = $now->format('Y-m-d');
            $zoneId = $wardId = null;
            $currentYear                = collect(explode('-', $request->fiYear))->first() ?? $now->year;
            $currentFyear               = $request->fiYear ?? getFinancialYear($currentDate);
            $startOfCurrentYear         = Carbon::createFromDate($currentYear, 4, 1);           // Start date of current financial year
            $startOfPreviousYear        = $startOfCurrentYear->copy()->subYear();               // Start date of previous financial year
            $previousFinancialYear      = getFinancialYear($startOfPreviousYear);

            #get financial  year 
            $refDate = $this->getFyearDate($currentFyear);
            $fromDates = $refDate['fromDate'];
            $uptoDates = $refDate['uptoDate'];

            #common function 
            $refDate = $this->getFyearDate($previousFinancialYear);
            $previousFromDate = $refDate['fromDate'];
            $previousUptoDate = $refDate['uptoDate'];

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId) {
                $userId = $request->userId;
            }

            # In Case of any logged in TC User
            if ($refUser->user_type == "TC") {
                $userId = $refUser->id;
            }

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            // DB::enableQueryLog();
            $data = ("SELECT 
              subquery.tran_id,
              subquery.tran_no,
              subquery.consumer_no,
              subquery.ward_name,
              subquery.zone_name,
              subquery.amount,
              subquery.address,
              subquery.transactiondate,
              subquery.user_name,
              subquery.payment_type,
              subquery.paymentstatus,
              subquery.applicant_name,
              subquery.road_type,
              subquery.tab_size,
              subquery.per_meter_amount,
              subquery.property_no,
              subquery.road_width,
              subquery.connection_type_amount,
              subquery.security_deposit,
              subquery.bore_charge,
              subquery.inspection_fee,
              subquery.pipe_connection_charge
              
     FROM (
         SELECT 
                water_trans.id as tran_id,
                water_trans.tran_date,
                water_trans.tran_no,
                water_second_consumers.consumer_no,water_trans.payment_mode,
                water_trans.amount,
                ulb_ward_masters.ward_name,
                zone_masters.zone_name,
                water_second_consumers.address,
                water_second_consumers.tab_size,
                water_trans.tran_date AS transactiondate,
                users.user_name,
                users.name,
                water_trans.payment_type,
                water_trans.payment_mode AS paymentstatus,
                water_consumer_owners.applicant_name,
                water_road_cutter_charges.road_type,
                water_road_cutter_charges.per_meter_amount,
                water_approval_application_details.property_no,
                water_site_inspections.road_width,
                water_connection_type_charges.amount as connection_type_amount,
                water_connection_type_charges.security_deposit,
                water_connection_type_charges.bore_charge,
                water_connection_type_charges.inspection_fee, 
                water_connection_type_charges.pipe_connection_charge 
          
        FROM water_trans 
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id=water_trans.ward_id
        LEFT JOIN water_second_consumers ON water_second_consumers.id=water_trans.related_id
        JOIN water_approval_application_details ON water_approval_application_details.id=water_second_consumers.apply_connection_id
        left JOIN water_consumer_owners ON water_consumer_owners.consumer_id=water_trans.related_id
        left Join zone_masters on zone_masters.id= water_second_consumers.zone_mstr_id
        left JOIN      water_site_inspections on water_site_inspections.apply_connection_id = water_approval_application_details.id
        JOIN      water_road_cutter_charges on water_road_cutter_charges.id = water_approval_application_details.road_type_id
        JOIN      water_connection_type_charges on water_connection_type_charges.id = water_second_consumers.connection_type_id
        -- JOIN water_consumer_collections on water_consumer_collections.transaction_id = water_trans.id
        LEFT JOIN users ON users.id=water_trans.emp_dtl_id
        where water_trans.related_id is not null 
        and water_site_inspections.status = 1
        and water_trans.status in (1, 2) 
        and tran_type = 'NEW_CONNECTION'
        and water_trans.tran_date between '$fromDate' and '$uptoDate'
                    " . ($zoneId ? " AND  water_second_consumers.zone_mstr_id = $zoneId" : "") . "
                    " . ($wardId ? " AND water_second_consumers.ward_mstr_id = $wardId" : "") . "
                    " . ($userId ? " AND water_trans.emp_dtl_id = $userId" : "") . "
                    " . ($paymentMode ? " AND water_trans.payment_mode = '$paymentMode'" : "") . "
        GROUP BY 
                water_trans.id,
                    water_second_consumers.consumer_no,
                    ulb_ward_masters.ward_name,
                    zone_masters.zone_name,
                    water_second_consumers.address,
                    users.user_name,
                    users.name,
                    water_consumer_owners.applicant_name,
                    water_trans.payment_type,
                    water_road_cutter_charges.road_type,
                    water_second_consumers.tab_size,
                    water_road_cutter_charges.per_meter_amount,
                    water_approval_application_details.property_no,    
                    water_site_inspections.road_width,
                    water_connection_type_charges.amount,
                    water_connection_type_charges.security_deposit,
                    water_connection_type_charges.bore_charge,
                    water_connection_type_charges.inspection_fee,
                    water_connection_type_charges.pipe_connection_charge 
     ) AS subquery");
            $data = DB::connection('pgsql_water')->select(DB::raw($data));
            $refData = collect($data);

            $refDetailsV2 = [
                "array" => $data,
                "security_deposit" => roundFigure($refData->pluck('security_deposit')->sum() ?? 0),
                "bore_charge" => roundFigure($refData->pluck('bore_charge')->sum() ?? 0),
                "inspection_fee" => roundFigure($refData->pluck('inspection_fee')->sum() ?? 0),
                "pipe_connection_charge" => roundFigure($refData->pluck('pipe_connection_charge')->sum() ?? 0),
                "totalAmount"   =>  roundFigure($refData->pluck('amount')->sum() ?? 0),
                "totalColletion" => $refData->pluck('tran_id')->count(),
                "currentDate"  => $currentDate
            ];
            $queryRunTime = (collect(DB::connection('pgsql_water'))->sum("time"));
            return responseMsgs(true, "collection Report", $refDetailsV2, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
}
