<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final readonly class HomeController
{
    public function __invoke(): View|RedirectResponse
    {
        // Если пользователь не авторизован, редиректим на страницу входа
        if (!auth()->check()) {
            $panel = Filament::getPanel('app');
            return redirect($panel->getLoginUrl());
        }

        return view('home.index');
    }
}
