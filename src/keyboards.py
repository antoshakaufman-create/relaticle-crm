"""
Keyboards - Генерация интерактивных клавиатур для бота.
Поддержка InlineKeyboardMarkup для критических действий и ReplyKeyboardMarkup для навигации.
"""
from telegram import (
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove,
    KeyboardButton
)
from typing import Optional


def create_preview_keyboard(
    cost_preview: float,
    final_cost: float,
    message_id: Optional[int] = None
) -> InlineKeyboardMarkup:
    """
    Создает клавиатуру для превью изображения с подтверждением финальной генерации.
    
    Args:
        cost_preview: Стоимость превью
        final_cost: Стоимость финальной генерации
        message_id: ID сообщения с превью (для callback_data)
        
    Returns:
        InlineKeyboardMarkup с кнопками действий
    """
    keyboard = [
        [
            InlineKeyboardButton(
                "✅ Подтвердить и создать 4K",
                callback_data=f"confirm_4k:{message_id or 0}"
            )
        ],
        [
            InlineKeyboardButton(
                "🔄 Изменить промпт",
                callback_data=f"edit_prompt:{message_id or 0}"
            ),
            InlineKeyboardButton(
                "❌ Отменить",
                callback_data=f"cancel:{message_id or 0}"
            )
        ]
    ]
    return InlineKeyboardMarkup(keyboard)


def create_main_menu_keyboard() -> ReplyKeyboardMarkup:
    """
    Минималистичное главное меню.
    Пользователь может просто отправить текст для генерации.
    """
    keyboard = [
        [
            KeyboardButton("⚙️ Настройки")
        ]
    ]
    return ReplyKeyboardMarkup(
        keyboard,
        resize_keyboard=True,
        is_persistent=True,
        one_time_keyboard=False,
        input_field_placeholder="Отправьте текст или фото для генерации..."
    )


def create_image_actions_keyboard(
    image_id: Optional[str] = None
) -> InlineKeyboardMarkup:
    """
    Создает клавиатуру действий после генерации изображения.
    
    Args:
        image_id: Уникальный ID изображения (для отслеживания)
        
    Returns:
        InlineKeyboardMarkup с действиями
    """
    keyboard = [
        [
            InlineKeyboardButton(
                "⚙️ Настройки",
                callback_data="open_settings"
            )
        ]
    ]
    return InlineKeyboardMarkup(keyboard)


def create_edit_keyboard() -> InlineKeyboardMarkup:
    """
    Создает клавиатуру для режима редактирования.
    
    Returns:
        InlineKeyboardMarkup с действиями редактирования
    """
    keyboard = [
        [
            InlineKeyboardButton(
                "✅ Применить изменения",
                callback_data="apply_edit"
            ),
            InlineKeyboardButton(
                "❌ Отменить",
                callback_data="cancel_edit"
            )
        ]
    ]
    return InlineKeyboardMarkup(keyboard)


def create_edit_mode_reply_keyboard() -> ReplyKeyboardMarkup:
    """
    Упрощенная клавиатура редактирования.
    Убраны лишние кнопки выбора типа ввода - бот поймет контекст сам.
    """
    keyboard = [
        [
            KeyboardButton("✅ Завершить редактирование"),
            KeyboardButton("❌ Отмена")
        ]
    ]
    return ReplyKeyboardMarkup(
        keyboard,
        resize_keyboard=True,
        is_persistent=True,
        one_time_keyboard=False,
        input_field_placeholder="Отправьте текст или фото для правки..."
    )


def create_edit_instructions_reply_keyboard() -> ReplyKeyboardMarkup:
    """
    Создает Reply Keyboard во время ожидания инструкций.
    Показывается после загрузки референсного изображения.
    
    Returns:
        ReplyKeyboardMarkup с кнопками для завершения редактирования
    """
    keyboard = [
        [
            KeyboardButton("✅ Применить изменения"),
            KeyboardButton("❌ Отменить")
        ]
    ]
    return ReplyKeyboardMarkup(
        keyboard,
        resize_keyboard=True,
        is_persistent=True,
        one_time_keyboard=False
    )


def create_multimodal_reply_keyboard() -> ReplyKeyboardMarkup:
    """
    Минималистичная клавиатура для сбора изображений.
    Подталкивает пользователя отправить текст для завершения.
    """
    keyboard = [
        [
            KeyboardButton("✅ Завершить (без текста)"),
            KeyboardButton("❌ Отмена")
        ]
    ]
    return ReplyKeyboardMarkup(
        keyboard,
        resize_keyboard=True,
        is_persistent=True,
        one_time_keyboard=False,
        input_field_placeholder="Отправьте еще фото или текст-промпт..."
    )


def remove_reply_keyboard() -> ReplyKeyboardRemove:
    """
    Удаляет Reply Keyboard, возвращая стандартную клавиатуру.
    
    Returns:
        ReplyKeyboardRemove для удаления клавиатуры
    """
    return ReplyKeyboardRemove()


def create_optimize_prompt_inline_keyboard() -> InlineKeyboardMarkup:
    """
    Inline клавиатура для выбора оптимизации промпта.
    """
    keyboard = [
        [
            InlineKeyboardButton("✅ Оптимизировать", callback_data="optimize_yes"),
            InlineKeyboardButton("⏭ Пропустить", callback_data="optimize_no")
        ]
    ]
    return InlineKeyboardMarkup(keyboard)


def create_settings_inline_keyboard(current_model: str = "zimage") -> InlineKeyboardMarkup:
    """
    Inline клавиатура для настройки разрешения, соотношения сторон и модели.
    """
    model_label = "🤖 Модель: Z-Image Turbo 🚀" if current_model == "zimage" else "🤖 Модель: Gemini 3 Pro ✨"
    
    keyboard = [
        [
            InlineKeyboardButton(model_label, callback_data="settings_model_switch")
        ],
        [
            InlineKeyboardButton("📐 1K", callback_data="set_res:1k"),
            InlineKeyboardButton("📐 2K", callback_data="set_res:2k"),
            InlineKeyboardButton("📐 4K", callback_data="set_res:4k")
        ],
        [
            InlineKeyboardButton("1:1", callback_data="set_aspect:1_1"),
            InlineKeyboardButton("4:5", callback_data="set_aspect:4_5"),
            InlineKeyboardButton("5:4", callback_data="set_aspect:5_4")
        ],
        [
            InlineKeyboardButton("16:9", callback_data="set_aspect:16_9"),
            InlineKeyboardButton("9:16", callback_data="set_aspect:9_16")
        ],
        [
            InlineKeyboardButton("4:3", callback_data="set_aspect:4_3"),
            InlineKeyboardButton("3:4", callback_data="set_aspect:3_4")
        ]
    ]
    return InlineKeyboardMarkup(keyboard)

