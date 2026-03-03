<?php

namespace App\Services\Contracts;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface AuditServiceInterface
{
    public function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?Request $request = null
    ): AuditLog;

    public function logCreate(Model $model, ?User $user = null, ?Request $request = null): AuditLog;

    public function logUpdate(Model $model, array $oldValues, ?User $user = null, ?Request $request = null): AuditLog;

    public function logDelete(Model $model, ?User $user = null, ?Request $request = null): AuditLog;

    public function logView(Model $model, ?User $user = null, ?Request $request = null): AuditLog;
}
