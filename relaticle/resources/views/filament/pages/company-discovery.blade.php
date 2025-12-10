<x-filament-panels::page>
    <form wire:submit="search">
        {{ $this->form }}

        <div class="flex justify-end mt-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="search">Найти</span>
                <span wire:loading wire:target="search">Поиск...</span>
            </x-filament::button>
        </div>
    </form>

    <div wire:loading wire:target="search" class="w-full text-center py-6">
        <x-filament::loading-indicator class="h-10 w-10 mx-auto" />
        <p class="text-sm text-gray-500 mt-2">Анализируем интернет и проверяем данные...</p>
    </div>

    @if(!empty($results))
        <h2 class="text-lg font-bold mt-6 mb-4">Результаты поиска</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($results as $company)
                <x-filament::section>
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold text-lg">{{ $company['name'] ?? 'Без названия' }}</h3>
                            <a href="{{ $company['url'] ?? '#' }}" target="_blank"
                                class="text-sm text-primary-600 hover:underline">
                                {{ $company['url'] ?? 'Нет сайта' }}
                            </a>
                        </div>
                        <x-filament::badge color="{{ ($company['confidence'] ?? 0) > 80 ? 'success' : 'warning' }}">
                            {{ $company['confidence'] ?? 0 }}% Sure
                        </x-filament::badge>
                    </div>

                    <p class="text-sm text-gray-600 mt-2 mb-4">
                        {{ $company['description'] ?? 'Нет описания' }}
                    </p>

                    <x-filament::button size="sm" color="success"
                        wire:click="importCompany('{{ $company['name'] }}', '{{ $company['url'] }}', '{{ $company['description'] }}')">
                        Добавить в CRM
                    </x-filament::button>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>