<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

final readonly class LocaleController
{
    /**
     * Switch application locale.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        // Validate locale
        $supportedLocales = ['en', 'ru'];
        
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = config('app.locale', 'ru');
        }

        // Set locale in session
        Session::put('locale', $locale);
        
        // Set locale for current request
        App::setLocale($locale);

        // Redirect back to previous page or home
        return redirect()->back()->with('locale_changed', $locale);
    }
}

