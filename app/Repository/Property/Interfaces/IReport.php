<?php

namespace App\Repository\Property\Interfaces;

use Illuminate\Http\Request;

interface IReport
{
    public function collectionReport(Request $request);
    public function safCollection(Request $request);
    public function safPropIndividualDemandAndCollection(Request $request);
    public function levelwisependingform(Request $request);
    public function levelformdetail(Request $request);
    public function levelUserPending(Request $request);
    public function userWiseWardWireLevelPending(Request $request);
    public function safSamFamGeotagging(Request $request);
    public function PropPaymentModeWiseSummery(Request $request);
    public function SafPaymentModeWiseSummery(Request $request);
    public function PropDCB(Request $request);
    public function PropWardWiseDCB(Request $request);
    public function PropFineRebate(Request $request);
    public function PropDeactedList(Request $request);
    public function propIndividualDemandCollection($request);
    public function gbsafIndividualDemandCollection($request);
    public function notPaidFrom2016($request);
    public function previousYearPaidButnotCurrentYear($request);
    public function notPayedFrom(Request $request);
    public function dueDemandPropList(Request $request);
    public function dcbPieChart($request);
    public function rebateNpenalty($request);
    public function adminDashReport(Request $request);
    public function tcCollectionReport();
    public function paymentModedealyCollectionRptV1(Request $request);
    public function individualDedealyCollectionRptV1(Request $request);
    public function tranDeactivatedList(Request $request);
}
