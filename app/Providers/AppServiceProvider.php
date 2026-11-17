<?php

namespace App\Providers;

use App\Exceptions\BusinessRuleException;
use App\Gateways\Cloudflare\CloudflarePdfRenderer;
use App\Gateways\Dompdf\DompdfPdfRenderer;
use App\Gateways\GatewayException;
use App\Gateways\PdfRenderer;
use App\Models\GeneratedDocument;
use App\Models\MasterDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, function (Application $app): PdfRenderer {
            $config = $app->make(Repository::class);

            return match ($driver = $config->string('pdf.driver')) {
                'cloudflare' => new CloudflarePdfRenderer(
                    $app->make(HttpFactory::class),
                    $config->string('pdf.cloudflare.account_id'),
                    $config->string('pdf.cloudflare.token'),
                    $config->integer('pdf.cloudflare.timeout'),
                ),
                'dompdf' => new DompdfPdfRenderer($app->make(Filesystem::class), storage_path('framework/cache/dompdf-fonts')),
                default => throw new InvalidArgumentException("Unknown PDF driver [{$driver}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureErrorPages();
    }

    /** Writes (any method but GET/HEAD) per user, or per IP for guests; page loads and polling are not counted. */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('writes', static fn (Request $request): Limit => $request->isMethodSafe()
            ? Limit::none()
            : Limit::perMinute(60)->by('writes:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Failed page loads render the Inertia `error` page instead of Laravel's: business rules and vendor outages
     * always (with their safe message), other errors only when debug is off, so local development keeps the
     * stack trace. Writes never get here with an error status: bootstrap/app.php turns those into back() + toast.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(static function (ExceptionResponse $response): ?ExceptionResponse {
            $status = $response->statusCode();
            $exception = $response->exception;

            if ($status < 400 || ($response->request->expectsJson() && ! $response->request->header('X-Inertia'))) {
                return null;
            }

            $message = match (true) {
                $exception instanceof BusinessRuleException => $exception->getMessage(),
                $exception instanceof GatewayException => GatewayException::userMessage(),
                default => null,
            };

            if ($message === null && (config('app.debug') || ! in_array($status, [403, 404, 419, 429, 500, 502, 503, 504], true))) {
                return null;
            }

            return $response->render('error', ['status' => $status, 'message' => $message])->withSharedData();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        if (app()->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
                logger()->warning('Lazy loading violation', ['model' => $model::class, 'relation' => $relation]);
            });
        }

        Relation::enforceMorphMap([
            'user' => User::class,
            'master_document' => MasterDocument::class,
            'generated_document' => GeneratedDocument::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
