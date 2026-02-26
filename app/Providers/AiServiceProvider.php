<?php

namespace SzentirasHu\Providers;

use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Ai\CommentaryService;
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

        $this->app->singleton(CommentaryService::class, function ($app) {
            return new CommentaryService(
                $app->make(\SzentirasHu\Service\Text\TextService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
