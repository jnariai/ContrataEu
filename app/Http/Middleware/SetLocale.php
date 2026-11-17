<?php

namespace App\Http\Middleware;

use App\Enums\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the UI locale: the user's saved language, then the guest's session choice, then Accept-Language, then English.
 */
class SetLocale
{
    public const string SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request)->laravelLocale());

        return $next($request);
    }

    private function resolve(Request $request): Language
    {
        if ($request->user() !== null) {
            return $request->user()->locale;
        }

        $fromSession = Language::tryFrom((string) $request->session()->get(self::SESSION_KEY));

        return $fromSession ?? Language::fromAcceptLanguage($request->header('Accept-Language'));
    }
}
