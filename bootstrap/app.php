<?php

use App\Exceptions\BusinessRuleException;
use App\Gateways\GatewayException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expected violations: shown to the user, never reported.
        $exceptions->dontReport(BusinessRuleException::class);

        // Business rules and vendor outages on web writes: back to the form with an error toast, in one place.
        $exceptions->render(static function (BusinessRuleException|GatewayException $e, Request $request) {
            $message = $e instanceof GatewayException ? GatewayException::userMessage() : $e->getMessage();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message, 'code' => $e instanceof BusinessRuleException ? $e->errorCode : 'gateway_unavailable'], $e->status);
            }

            // Page loads keep the exception's status; AppServiceProvider::configureErrorPages() then swaps this plain
            // response for the Inertia error page with the same safe message.
            if ($request->isMethodSafe()) {
                return response($message, $e->status);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        });

        // Too many writes (the `writes` limiter): stay on the page with a toast instead of an error page.
        $exceptions->render(static function (ThrottleRequestsException $e, Request $request) {
            if ($request->isMethodSafe() || ! $request->header('X-Inertia')) {
                return null;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Too many requests in a short time. Wait a minute and try again.')]);

            return back();
        });
    })->create();
