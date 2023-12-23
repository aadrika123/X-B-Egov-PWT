<?php

namespace App\Console\Commands;

use App\Http\Controllers\Property\ReportController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class LiveDashboardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liveDashboard:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $controller = App::makeWith(ReportController::class);
        $controller->liveDashboardUpdate();
        return 0;
    }
}
