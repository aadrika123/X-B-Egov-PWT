<?php

namespace App\Repository\Water\Interfaces;

use Illuminate\Http\Request;

/**
 * | ---------------------- Interface for the New Connections for Water ----------------------- |
 * | Created On-07-10-2022 
 * | Created By - Anshu Kumar
 * | Updated By - Sam kerketta
 */

interface iNewConnection
{
   public function store(Request $req);                                 // Apply for new water connection
   public function postNextLevel($req);                                 // Approval in the workflow level
   public function getApplicationsDetails($request);                    // Get the application list for the workflow
   public function getApproveApplicationsDetails($request);                    // Get the application list for the workflow
   public function approvalRejectionWater($request,$roleId);            // Final Approval and Rejection of water Applications
   public function getApprovedWater($request);                          // Get the details of the Approved water Appication
   
}
