"""
Handlers package - Модульная структура обработчиков бота.
Все обработчики организованы по функциональным модулям.
"""
from .base import start_handler, stats_handler, main_menu_handler
from .callbacks import (
    callback_regenerate,
    callback_save,
    callback_set_resolution,
    callback_set_aspect_ratio,
    callback_open_settings,
    callback_toggle_model
)
from .generation_flow import create_generation_flow_handler
from .edit_flow import create_edit_flow_handler, callback_edit_image

# Экспортируем функции создания ConversationHandler
__all__ = [
    "start_handler",
    "stats_handler", 
    "main_menu_handler",
    "callback_regenerate",
    "callback_save",
    "callback_set_resolution",
    "callback_set_aspect_ratio",
    "callback_open_settings",
    "callback_toggle_model",
    "create_generation_flow_handler",
    "create_edit_flow_handler",
    "callback_edit_image",
]
