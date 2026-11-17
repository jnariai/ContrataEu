<?php

namespace App\Providers;

use App\Models\GeneratedDocument;
use App\Models\MasterDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
