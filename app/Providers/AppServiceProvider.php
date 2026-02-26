<?php

namespace SzentirasHu\Providers;

use SzentirasHu\Service\Ai\AiPromptService;
use Illuminate\Support\ServiceProvider;
use SzentirasHu\Service\Editor\EditorService;
use Twig\Environment;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Environment $twig)
    {
        $twig->addExtension(new MarkdownExtension());
        $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
            public function load($class) {
                if (MarkdownRuntime::class === $class) {
                    return new MarkdownRuntime(new DefaultMarkdown());
                } else {
                    return null;
                }
            }
        });
        
        // Add isEditor function to Twig
        $twig->addFunction(new \Twig\TwigFunction('isEditor', function () {
            return app(EditorService::class)->currentIsEditor();
        }));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(EditorService::class, function ($app) {
            return new EditorService();
        });
        
        $this->app->alias(EditorService::class, 'editor');

        // Register AI Prompt Service
        $this->app->singleton(AiPromptService::class, function ($app) {
            return new AiPromptService($app['config']);
        });
        
        $this->app->alias(AiPromptService::class, 'ai-prompt');
    }
}
