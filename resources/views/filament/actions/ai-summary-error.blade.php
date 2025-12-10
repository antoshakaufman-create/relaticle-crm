<div class="space-y-4">
    <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <div class="flex-1">
                <h4 class="text-sm font-medium text-red-800 dark:text-red-200 mb-1">
                    Ошибка генерации AI Summary
                </h4>
                <p class="text-sm text-red-700 dark:text-red-300 mb-2">
                    {{ $error }}
                </p>
                @if(isset($hint))
                    <p class="text-xs text-red-600 dark:text-red-400">
                        {{ $hint }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

