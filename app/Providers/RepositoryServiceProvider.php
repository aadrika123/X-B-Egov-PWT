<?php

namespace App\Providers;

use App\Repository\Citizen\CitizenRepository;
use App\Repository\Citizen\iCitizenRepository;

use App\Repository\Cluster\Concrete\ClusterRepository;
use App\Repository\Cluster\Interfaces\iCluster;
use App\Repository\Dashboard\IStateDashboard;
use App\Repository\Dashboard\StateDashboard;
use App\Repository\Grievance\Concrete\NewGrievanceRepository;
use App\Repository\Grievance\Interfaces\iGrievance;

use App\Repository\Menu\Interface\iMenuRepo;
use App\Repository\Menu\Concrete\MenuRepo;
use App\Repository\Notice\INotice;
use App\Repository\Notice\Notice;
use App\Repository\Payment\Concrete\PaymentRepository;
use App\Repository\Payment\Interfaces\iPayment;

use App\Repository\Property\Concrete\SafRepository;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Repository\Property\Concrete\ConcessionRepository;
use App\Repository\Property\Interfaces\iConcessionRepository;
use App\Repository\Property\Concrete\SafReassessRepo;
use App\Repository\Property\Interfaces\iSafReassessRepo;
use App\Repository\Property\Concrete\ObjectionRepository;
use App\Repository\Property\Interfaces\iObjectionRepository;
use App\Repository\Property\Concrete\PropertyDeactivate;
use App\Repository\Property\Interfaces\IPropertyDeactivate;
use App\Repository\Property\Concrete\RainWaterHarvestingRepo;
use App\Repository\Property\Interfaces\iRainWaterHarvesting;
use App\Repository\Trade\ITrade;
use App\Repository\Trade\Trade;

use App\Repository\Water\Concrete\NewConnectionRepository;
use App\Repository\Water\Interfaces\iNewConnection;
use App\Repository\Water\Concrete\WaterNewConnection;
use App\Repository\Water\Interfaces\IWaterNewConnection;

use App\Repository\WorkflowMaster\Concrete\WorkflowMasterRepository;
use App\Repository\WorkflowMaster\Interface\iWorkflowMasterRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowRoleRepository;
use App\Repository\WorkflowMaster\Interface\iWorkflowRoleRepository;
use App\Repository\WorkflowMaster\Concrete\WfWorkflowRepository;
use App\Repository\WorkflowMaster\Interface\iWfWorkflowRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowRoleMapRepository;
use App\Repository\WorkflowMaster\Interface\iWorkflowRoleMapRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowRoleUserMapRepository;
use App\Repository\WorkflowMaster\Interface\iWorkflowRoleUserMapRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowWardUserRepository;
use App\Repository\WorkflowMaster\Interface\iWorkflowWardUserRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use App\Repository\WorkflowMaster\Interface\iWorkflowMapRepository;

use App\Repository\Property\Concrete\CalculatorRepository;
use App\Repository\Property\Concrete\DocumentOperationRepo;
use App\Repository\Property\Concrete\PropertyBifurcation;
use App\Repository\Property\Concrete\PropertyDetailsRepo;
use App\Repository\Property\Concrete\Report;
use App\Repository\Property\Concrete\SafDemandRepo;
use App\Repository\Property\Interfaces\iCalculatorRepository;
use App\Repository\Property\Interfaces\iDocumentOperationRepo;
use App\Repository\Property\Interfaces\IPropertyBifurcation;
use App\Repository\Property\Interfaces\iPropertyDetailsRepo;
use App\Repository\Property\Interfaces\IReport;
use App\Repository\Property\Interfaces\iSafDemandRepo;
use App\Repository\Trade\IReport as TradeIReport;
use App\Repository\Trade\ITradeCitizen;
use App\Repository\Trade\ITradeNotice;
use App\Repository\Trade\Report as TradeReport;
use App\Repository\Trade\TradeCitizen;
use App\Repository\Trade\TradeNotice;

use App\Repository\Water\Concrete\Consumer;
use App\Repository\Water\Interfaces\IConsumer;
use App\Repository\Workflow\Concrete\WorkflowRepository;
use App\Repository\Workflow\Interface\iWorkflowRepo;
use App\Repository\Workflow\Interface\iWorkflowRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * | ------------ Provider for the Binding of Interface and Concrete Class of the Repository --------------------------- |
     * | Created On- 07-10-2022 
     * | Created By- Anshu Kumar
     */
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        #------trade module----------
        $this->app->bind(ITrade::class, Trade::class);
        $this->app->bind(ITradeCitizen::class, TradeCitizen::class);
        $this->app->bind(ITradeNotice::class, TradeNotice::class);
        $this->app->bind(TradeIReport::class, TradeReport::class);
        #------water module----------
        $this->app->bind(iNewConnection::class, NewConnectionRepository::class);
        $this->app->bind(IWaterNewConnection::class, WaterNewConnection::class);
        $this->app->bind(IConsumer::class, Consumer::class);

        // Property
        $this->app->bind(iSafRepository::class, SafRepository::class);
        $this->app->bind(iSafReassessRepo::class, SafReassessRepo::class);
        $this->app->bind(IPropertyDeactivate::class, PropertyDeactivate::class);
        // $this->app->bind(iRainWaterHarvesting::class, RainWaterHarvestingRepo::class);
        $this->app->bind(IPropertyBifurcation::class, PropertyBifurcation::class);
        $this->app->bind(iPropertyDetailsRepo::class, PropertyDetailsRepo::class);
        // $this->app->bind(iDocumentOperationRepo::class, DocumentOperationRepo::class);
        // $this->app->bind(iSafDemandRepo::class, SafDemandRepo::class);
        $this->app->bind(IReport::class, Report::class);

        //menu permission

        // Workflow Master
        // $this->app->bind(iWorkflowMasterRepository::class, WorkflowMasterRepository::class);
        $this->app->bind(iWorkflowRoleRepository::class, WorkflowRoleRepository::class);
        // $this->app->bind(iWfWorkflowRepository::class, WfWorkflowRepository::class);
        // $this->app->bind(iWorkflowRoleMapRepository::class, WorkflowRoleMapRepository::class);
        $this->app->bind(iWorkflowRoleUserMapRepository::class, WorkflowRoleUserMapRepository::class);
        // $this->app->bind(iWorkflowWardUserRepository::class, WorkflowWardUserRepository::class);
        $this->app->bind(iWorkflowMapRepository::class, WorkflowMap::class);

        // Grievance
        $this->app->bind(iGrievance::class, NewGrievanceRepository::class);

        //payment gatewway
        $this->app->bind(iPayment::class, PaymentRepository::class);

        //Property Calculator
        $this->app->bind(iCalculatorRepository::class, CalculatorRepository::class);

        //citizen 
        $this->app->bind(iCitizenRepository::class, CitizenRepository::class);

        //Concession
        $this->app->bind(iConcessionRepository::class, ConcessionRepository::class);

        //Objection
        $this->app->bind(iObjectionRepository::class, ObjectionRepository::class);

        //Cluster
        $this->app->bind(iCluster::class, ClusterRepository::class);

        // Menues
        $this->app->bind(iMenuRepo::class, MenuRepo::class);

        //traits
        $this->app->bind(iWorkflowRepository::class, WorkflowRepository::class);

        #------Notice module----------
        $this->app->bind(INotice::class, Notice::class);

        #-------State Dashboard-------
        $this->app->bind(IStateDashboard::class, StateDashboard::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
