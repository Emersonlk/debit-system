<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
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
        $user = $user ?? auth()->user();
        $request = $request ?? request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
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
