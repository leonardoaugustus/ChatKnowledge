<?php

use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\SetOrganizationUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetOrganizationUrlDefaults::class,
        ]);

        $middleware->alias([
            'active.organization' => EnsureActiveOrganization::class,
            'subscribed' => EnsureSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
