<?php
/**
 */

namespace SzentirasHu\Providers;

use Illuminate\Support\ServiceProvider;
use SzentirasHu\Http\ViewComposers\RufAdComposer;

class ViewComposerServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        view()->composer('menu', 'SzentirasHu\\Http\\ViewComposers\\MenuComposer');
        view()->composer('bookAbbrevList', '\\SzentirasHu\\Http\\ViewComposers\\BookAbbrevListComposer');
        // Add RufAdComposer only to RUF routes
        view()->composer('*', function ($view) {
            $path = request()->path();
            if ($path === 'RUF' || str_starts_with($path, 'RUF/')) {
                app(RufAdComposer::class)->compose($view);
            }
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {

    }
}