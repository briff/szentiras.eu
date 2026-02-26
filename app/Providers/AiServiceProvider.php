<?php

namespace App\Providers;

use App\Service\Ai\AiPromptService;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AiPromptService::class, function ($app) {
            return new AiPromptService($app['config']);
        });

        $this->app->alias(AiPromptService::class, 'ai-prompt');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
