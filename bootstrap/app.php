<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use SzentirasHu\Http\Middleware\CheckCommentaryGeneration;
use SzentirasHu\Http\Middleware\CheckEditor;
use SzentirasHu\Http\Middleware\FillAnonymousIdFromCookie;
use SzentirasHu\Http\Middleware\SameOrigin;
use SzentirasHu\Http\Middleware\ValidateAnonymousId;
use SzentirasHu\Http\Middleware\VerifyApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12'
        ]);    
        $middleware->alias([
            'anonymousId' => ValidateAnonymousId::class,
            'editor' => CheckEditor::class,
            'commentaryGeneration' => CheckCommentaryGeneration::class,
            'apiKey' => VerifyApiKey::class,
            'same-origin' => SameOrigin::class,
        ]);
        $middleware->web(append: [FillAnonymousIdFromCookie::class]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('szentiras:fetch-daily-reading')
            ->dailyAt('04:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();