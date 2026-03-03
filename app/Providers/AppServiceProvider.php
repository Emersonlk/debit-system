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
use App\Services\AuditService;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\ClienteServiceInterface;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\NotificacaoServiceInterface;
use App\Services\Contracts\PromissoriaImageExtractorInterface;
use App\Services\Contracts\PromissoriaServiceInterface;
use App\Services\ClienteService;
use App\Services\DashboardService;
use App\Services\NotificacaoService;
use App\Services\PromissoriaImageExtractorService;
use App\Services\PromissoriaService;
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

        // Bind Services
        $this->app->bind(AuditServiceInterface::class, AuditService::class);
        $this->app->bind(ClienteServiceInterface::class, ClienteService::class);
        $this->app->bind(PromissoriaServiceInterface::class, PromissoriaService::class);
        $this->app->bind(PromissoriaImageExtractorInterface::class, PromissoriaImageExtractorService::class);
        $this->app->bind(NotificacaoServiceInterface::class, NotificacaoService::class);
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
