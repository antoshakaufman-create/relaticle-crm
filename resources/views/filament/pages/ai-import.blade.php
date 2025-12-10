<x-filament-panels::page>
    <x-filament-panels::form wire:submit="analyze">
        {{ $this->form }}

        <div class="flex justify-end mt-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                Analyze File with AI
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @if($analyzed)
        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border dark:border-gray-800">
            <h3 class="text-lg font-medium mb-4">AI Suggested Mapping</h3>

            <div class="grid grid-cols-2 gap-4">
                @foreach($mapping as $header => $field)
                    <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded shadow-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $header }}</span>
                        <span class="text-gray-500">→</span>
                        <span class="px-2 py-1 bg-primary-100 text-primary-700 rounded text-sm font-mono">
                            {{ $field ?? 'Ignore' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end mt-6">
                <x-filament::button wire:click="runImport" color="success">
                    Confirm & Run Import
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>