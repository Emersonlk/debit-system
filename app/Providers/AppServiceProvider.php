<?php

namespace App\Providers;

use App\Repositories\ClienteRepository;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Repositories\PromissoriaRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        //
    }
}
