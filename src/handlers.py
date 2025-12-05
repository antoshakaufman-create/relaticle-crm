"""
Handlers - Обработчики команд и сообщений Telegram бота.
УСТАРЕЛО: Этот файл сохранен для обратной совместимости.
Все обработчики перенесены в модульную структуру src/handlers/

Новая структура:
- src/handlers/base.py - базовые обработчики (start, stats, main_menu)
- src/handlers/generation_flow.py - ConversationHandler для генерации
- src/handlers/edit_flow.py - ConversationHandler для редактирования
- src/handlers/callbacks.py - обработчики CallbackQuery

Этот файл можно удалить после полного тестирования новой архитектуры.

Для обратной совместимости, используйте прямые импорты из новых модулей:
- from src.handlers.base import start_handler, stats_handler, main_menu_handler
- from src.handlers.generation_flow import create_generation_flow_handler
- from src.handlers.edit_flow import create_edit_flow_handler
- from src.handlers.callbacks import callback_regenerate, callback_save
"""

# Файл пуст - все обработчики перенесены в src/handlers/
