<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A API é autenticada exclusivamente via Sanctum (guard 'sanctum'), mas as roles e
 * permissions foram originalmente seedadas com guard_name='web' (o default estático
 * de config('auth.defaults.guard') fora de uma requisição HTTP, antes desta correção).
 * Isso fazia Role::findByName()/Permission::findByName() (chamados sem guard explícito
 * em PermissionController) resolverem o guard 'sanctum' em runtime e não encontrarem
 * as roles/permissions, que estavam salvas como 'web'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('guard_name', 'web')->update(['guard_name' => 'sanctum']);
        DB::table('permissions')->where('guard_name', 'web')->update(['guard_name' => 'sanctum']);
    }

    public function down(): void
    {
        DB::table('roles')->where('guard_name', 'sanctum')->update(['guard_name' => 'web']);
        DB::table('permissions')->where('guard_name', 'sanctum')->update(['guard_name' => 'web']);
    }
};
