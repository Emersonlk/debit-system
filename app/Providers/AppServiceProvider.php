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
use App\Services\Dashboard\Metrics\ClientesTotalMetric;
use App\Services\Dashboard\Metrics\DistribuicaoClienteMetric;
use App\Services\Dashboard\Metrics\MaioresDividasMetric;
use App\Services\Dashboard\Metrics\PromissoriasResumoMetric;
use App\Services\Dashboard\Metrics\RecebimentosPeriodoMetric;
use App\Services\Dashboard\Metrics\ResumoVencimentoMetric;
use App\Services\Dashboard\Metrics\UltimosPagamentosMetric;
use App\Services\NotificacaoService;
use App\Queue\TenantAwareCallQueuedHandler;
use App\Services\PromissoriaImageExtractorService;
use App\Services\PromissoriaService;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Support\Facades\Queue;

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
        // Contexto de empresa (tenant): uma instância por requisição/job.
        $this->app->scoped(CurrentCompany::class);

        // Jobs enfileirados restauram o contexto de empresa antes de desserializar.
        $this->app->bind(CallQueuedHandler::class, TenantAwareCallQueuedHandler::class);

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

        // Dashboard: métricas registradas por tag (OCP – novas métricas = novo binding na tag)
        $this->app->tag([
            ClientesTotalMetric::class,
            PromissoriasResumoMetric::class,
            ResumoVencimentoMetric::class,
            RecebimentosPeriodoMetric::class,
            DistribuicaoClienteMetric::class,
            MaioresDividasMetric::class,
            UltimosPagamentosMetric::class,
        ], 'dashboard_metrics');
        $this->app->when(DashboardService::class)->needs('$metrics')->giveTagged('dashboard_metrics');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Carimba a empresa atual no payload do job, para que o worker consiga
        // restaurá-la depois. Sem contexto no enfileiramento, nada é carimbado — o
        // job então falha ao tocar dados tenant-aware, em vez de rodar globalmente.
        Queue::createPayloadUsing(function () {
            $currentCompany = $this->app->make(CurrentCompany::class);

            return $currentCompany->has()
                ? ['company_id' => $currentCompany->id()]
                : [];
        });
    }
}
