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
        
        // Add unreadMessageCount function to Twig
        $twig->addFunction(new \Twig\TwigFunction('unreadMessageCount', function () {
            $token = session('anonymous_token');
            if (!$token) {
                return 0;
            }
            
            $anonymousId = \SzentirasHu\Data\Entity\AnonymousId::where('token', $token)->first();
            if (!$anonymousId) {
                return 0;
            }
            
            return \SzentirasHu\Data\Entity\ContactMessage::where('receiver_anonymous_id', $anonymousId->id)
                ->where('is_read', false)
                ->count();
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

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

    }
}
