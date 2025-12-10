<div class="flex items-center justify-center space-x-4 mt-4">
    <a href="{{ route('locale.switch', 'en') }}"
        class="text-sm font-medium {{ app()->getLocale() === 'en' ? 'text-primary-600 dark:text-primary-400 font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
        🇺🇸 English
    </a>
    <span class="text-gray-300 dark:text-gray-600">|</span>
    <a href="{{ route('locale.switch', 'ru') }}"
        class="text-sm font-medium {{ app()->getLocale() === 'ru' ? 'text-primary-600 dark:text-primary-400 font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
        🇷🇺 Русский
    </a>
</div>