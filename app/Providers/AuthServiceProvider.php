<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Auth\SitamaUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // User web dipulihkan dari profil endpoint SITAMA di session.
        Auth::provider('sitama', fn ($app) => new SitamaUserProvider($app['session.store']));
    }
}
