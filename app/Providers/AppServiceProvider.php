<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Promissoria;
use App\Models\User;
use App\Policies\ClientePolicy;
use App\Policies\PromissoriaPolicy;
use App\Policies\UserPolicy;
use App\Repositories\ClienteRepository;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Repositories\PromissoriaRepository;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Cliente::class => ClientePolicy::class,
        Promissoria::class => PromissoriaPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repositories
        $this->app->bind(ClienteRepositoryInterface::class, ClienteRepository::class);
        $this->app->bind(PromissoriaRepositoryInterface::class, PromissoriaRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
