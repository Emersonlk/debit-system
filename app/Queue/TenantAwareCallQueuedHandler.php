<?php

namespace App\Queue;

use App\Exceptions\TenantNotFoundException;
use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\CallQueuedHandler;

/**
 * Restaura o contexto de empresa antes de o job ser desserializado e executado.
 *
 * Por que aqui e não em um job middleware: CallQueuedHandler::call() desserializa o
 * comando (getCommand) ANTES de dispatchThroughMiddleware(). E a desserialização de
 * uma Notification já toca o banco — Illuminate\Notifications\Notification usa
 * SerializesModels, então propriedades como `Promissoria $promissoria` viram
 * ModelIdentifier e são reconsultadas, junto com as relações que estavam carregadas
 * (loadMissing). Essa reconsulta de relação passa pelo Global Scope e falharia sem
 * contexto. Portanto o contexto precisa existir já no unserialize — antes de
 * qualquer middleware.
 *
 * O company_id chega no payload via Queue::createPayloadUsing() (AppServiceProvider),
 * que só o inclui quando existe contexto no momento do enfileiramento.
 *
 * Raio de impacto: jobs sem company_id no payload seguem exatamente o caminho padrão.
 */
class TenantAwareCallQueuedHandler extends CallQueuedHandler
{
    public function call(Job $job, array $data)
    {
        $companyId = $job->payload()['company_id'] ?? null;

        // Job sem tenant: comportamento padrão do framework, inalterado.
        if ($companyId === null) {
            parent::call($job, $data);

            return;
        }

        $company = Company::find($companyId);

        // Fail-closed: empresa removida não vira processamento global nem silencioso.
        if ($company === null) {
            throw TenantNotFoundException::forId((int) $companyId);
        }

        app(CurrentCompany::class)->runAs($company, function () use ($job, $data) {
            parent::call($job, $data);
        });
    }
}
