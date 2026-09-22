<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Contracts\AuditServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditService implements AuditServiceInterface
{
    /**
     * Registra uma ação no log de auditoria
     */
    public function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?Request $request = null
    ): AuditLog {
        $user = $user ?? Auth::user();
        $request = $request ?? request();

        $companyId = $this->resolverEmpresa($action, $model, $user);

        $auditLog = new AuditLog([
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Atribuição fora do $fillable: company_id nunca pode ser controlado por
        // atribuição em massa, só pelo valor resolvido aqui.
        $auditLog->setAttribute('company_id', $companyId);
        $auditLog->save();

        return $auditLog;
    }

    /**
     * Empresa à qual o registro de auditoria pertence.
     *
     * O model auditado é a fonte primária — é ele que define de quem é o dado. O
     * usuário é apenas fallback, para models que não pertencem a uma empresa. Essa
     * ordem é o que mantém o log correto quando um Super Admin (company_id nulo)
     * agir sobre dados da empresa X: o log pertence a X, não a "nenhuma empresa".
     *
     * Exceção deliberada ao fail-closed adotado no isolamento de tenant: quando a
     * empresa não é determinável, o log é gravado mesmo assim (com company_id nulo)
     * e o caso vira um warning. Auditoria é observabilidade, não barreira de acesso —
     * derrubar uma operação de negócio já concluída por causa do log seria pior do
     * que registrar um log órfão e sinalizar o problema.
     */
    private function resolverEmpresa(string $action, Model $model, ?User $user): ?int
    {
        $companyId = $model->getAttribute('company_id') ?? $user?->company_id;

        if ($companyId === null) {
            Log::warning('AuditLog criado sem empresa determinável.', [
                'action' => $action,
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'user_id' => $user?->id,
            ]);

            return null;
        }

        return (int) $companyId;
    }

    /**
     * Registra criação de modelo
     */
    public function logCreate(Model $model, ?User $user = null, ?Request $request = null): AuditLog
    {
        return $this->log(
            'create',
            $model,
            null,
            $model->getAttributes(),
            $user,
            $request
        );
    }

    /**
     * Registra atualização de modelo
     */
    public function logUpdate(Model $model, array $oldValues, ?User $user = null, ?Request $request = null): AuditLog
    {
        return $this->log(
            'update',
            $model,
            $oldValues,
            $model->getAttributes(),
            $user,
            $request
        );
    }

    /**
     * Registra exclusão de modelo
     */
    public function logDelete(Model $model, ?User $user = null, ?Request $request = null): AuditLog
    {
        return $this->log(
            'delete',
            $model,
            $model->getAttributes(),
            null,
            $user,
            $request
        );
    }

    /**
     * Registra visualização de modelo
     */
    public function logView(Model $model, ?User $user = null, ?Request $request = null): AuditLog
    {
        return $this->log(
            'view',
            $model,
            null,
            null,
            $user,
            $request
        );
    }
}
