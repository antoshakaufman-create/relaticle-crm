<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from session or use default
        $locale = Session::get('locale', config('app.locale', 'ru'));
        
        // Validate locale
        $supportedLocales = ['en', 'ru'];
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = config('app.locale', 'ru');
        }
        
        // Set locale
        App::setLocale($locale);
        
        return $next($request);
    }
}

