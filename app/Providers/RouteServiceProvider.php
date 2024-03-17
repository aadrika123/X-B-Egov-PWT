<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            // Workflow Api
            Route::prefix('api/workflow')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/workflow.php'));


            Route::prefix('api/water')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/water.php'));

            Route::prefix('api/trade')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/trade.php'));

            // Property Api
            Route::prefix('api/property')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/property.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            //Grievance
            Route::prefix('api/grievance')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/grievance.php'));

            //payment
            Route::prefix('api/payment')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/payment.php'));

            //dashboard
            Route::prefix('api/dashboard')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/dashboard.php'));

            // Permissions
            Route::prefix('api/permissions')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/permission.php'));

            // Notice Api
            Route::prefix('api/notice')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/notice.php'));
            // Bug Api
            Route::prefix('api/bug')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/bug.php'));
        });
    }

    /**
     * Configure the rate limiters for the application. dsfg
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(200)->by(optional($request->user())->id ?: $request->ip());
        });
    }


}
