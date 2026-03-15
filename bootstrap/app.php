<?php

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\EnsureOrganization;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.2fa' => EnsureAdminTwoFactor::class,
            'onboarded' => EnsureOnboarded::class,
            'organization' => EnsureOrganization::class,
            'workspace' => EnsureWorkspace::class,
            '2fa' => EnsureTwoFactor::class,
            // Legacy aliases (backward compat)
            'ensure-organization' => EnsureOrganization::class,
            'ensure-workspace' => EnsureWorkspace::class,
        ]);

        $middleware->priority([
            EnsureOnboarded::class,
            EnsureOrganization::class,
            SubstituteBindings::class,
        ]);

        // Stripe webhooks use HMAC-SHA256 signature verification, not CSRF tokens
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->appendToGroup('web', [
            HandleImpersonation::class,
            SecurityHeaders::class,
            AddCspHeaders::class,
        ]);

        $middleware->appendToGroup('app', [
            'auth',
            'verified',
            'onboarded',
            'organization',
            '2fa',
            'workspace',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
