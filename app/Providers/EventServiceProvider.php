<?php

namespace App\Providers;

use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropPropertyUpdateRequest;
use App\Models\Property\PropSafJahirnamaDoc;
use App\Observers\Property\JahirnamaObserver;
use App\Observers\Property\PropActiveSafObserver;
use App\Observers\PropUpdateRequestObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        PropActiveSaf::observe(PropActiveSafObserver::class);
        PropPropertyUpdateRequest::observe(PropUpdateRequestObserver::class);
        PropSafJahirnamaDoc::observe(JahirnamaObserver::class);
    }
}
