<?php

namespace App\Http\Middleware;

use App\Enums\Language;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'email_verified_at' => $request->user()->email_verified_at?->toIso8601ZuluString(),
                ],
            ],
            'locale' => fn (): array => $this->currentLanguage(),
            'languages' => Inertia::once(fn (): array => Language::options()),
            'translations' => Inertia::once(fn (): array => $this->translations())->as('translations.'.app()->getLocale()),
        ];
    }

    /** @return array{value: string, label: string, htmlLang: string} */
    private function currentLanguage(): array
    {
        $language = Language::fromLaravelLocale(app()->getLocale());

        return ['value' => $language->value, 'label' => $language->label(), 'htmlLang' => $language->htmlLang()];
    }

    /**
     * The current locale's JSON translations: the same file `__()` reads, so PHP and React share one source.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
