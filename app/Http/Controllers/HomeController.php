<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final readonly class HomeController
{
    public function __invoke(): View|RedirectResponse
    {
        // Если пользователь не авторизован, редиректим на страницу регистрации
        if (!auth()->check()) {
            return redirect()->route('filament.app.auth.register');
        }

        // Для авторизованных пользователей - редирект на панель
        return redirect('/app');
    }
}
