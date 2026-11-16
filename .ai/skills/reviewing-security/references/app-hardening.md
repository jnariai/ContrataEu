# Reference: application hardening defaults

Defaults worth having in `AppServiceProvider`: strict models, protection against destructive commands in production, immutable dates, password defaults, HTTP timeouts, rate limiters and HTTPS.

The provider below is a **reference**. Never copy it over the project's provider; merge only the parts the user approves:

| Part | Effect | Legacy caution |
|---|---|---|
| `configureModels()` | Lazy loading throws outside production and is logged in production; strict attributes outside production | Expect lazy-loading failures in tests: fix them, or enable step by step |
| `configureDatabase()` | Blocks `migrate:fresh`/`db:wipe` in production | None |
| `configureDates()` | `CarbonImmutable` everywhere | Code that mutates dates (`$date->addDay()` without assignment) breaks. Search first; often skip on legacy |
| `configurePasswords()` | `Password::defaults()` (12 + uncompromised in production) | Only affects rules that use `Password::defaults()` |
| `configureHttpClient()` | Global HTTP timeouts | Check long-running integrations |
| `configureRateLimiting()` | `api` and `auth` limiters | Don't replace existing limiters with the same name |
| `configureUrls()` | HTTPS in production | Behind a proxy, configure trusted proxies first |

Run the full test suite after merging, and report the failures each part caused.

```php
// app/Providers/AppServiceProvider.php (reference — merge, don't copy)
<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Opinionated application hardening. Merge into your existing provider.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind external-boundary interfaces here, e.g.:
        // $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureDatabase();
        $this->configureDates();
        $this->configurePasswords();
        $this->configureHttpClient();
        $this->configureRateLimiting();
        $this->configureUrls();
    }

    private function configureModels(): void
    {
        $strict = ! $this->app->isProduction();

        // Lazy loading is always detected: it throws locally/CI and is logged in production.
        Model::preventLazyLoading();
        Model::preventSilentlyDiscardingAttributes($strict);
        Model::preventAccessingMissingAttributes($strict);

        if ($strict) {
            return;
        }

        // Production: never break a user's request over an N+1 — log it and fix it.
        Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
            Log::warning('N+1: lazy loaded relation', [
                'model' => $model::class,
                'relation' => $relation,
            ]);
        });
    }

    private function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(8));
    }

    private function configureHttpClient(): void
    {
        Http::globalOptions([
            'timeout' => 10,
            'connect_timeout' => 3,
        ]);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Stricter limiter for enumeration-prone endpoints (login, reset, OTP).
        RateLimiter::for('auth', static fn (Request $request): array => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }

    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
```
