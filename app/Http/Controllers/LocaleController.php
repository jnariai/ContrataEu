<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Change the UI language: saved on the user when signed in, in the session for guests.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $language = $request->language();

        if ($request->user() !== null) {
            $request->user()->switchLocale($language);
        } else {
            $request->session()->put(SetLocale::SESSION_KEY, $language->value);
        }

        return back();
    }
}
