"""
Callbacks - Обработчики CallbackQuery для кнопок действий.
Эти обработчики не требуют ConversationHandler и обрабатываются отдельно.
"""
from telegram import Update, CallbackQuery
from telegram.ext import ContextTypes

from src.middleware import require_auth
from src.keyboards import create_settings_inline_keyboard
from src.config import (
    RESOLUTION_1K,
    RESOLUTION_2K,
    RESOLUTION_4K,
    ASPECT_RATIO_1_1,
    ASPECT_RATIO_16_9,
    ASPECT_RATIO_9_16,
    ASPECT_RATIO_4_3,
    ASPECT_RATIO_3_4,
    ASPECT_RATIO_4_5,
    ASPECT_RATIO_5_4,
)
from src.handlers.settings_utils import (
    RESOLUTION_LABELS,
    ASPECT_LABELS,
    MODEL_LABELS,
    get_user_resolution,
    get_user_aspect_ratio,
    get_user_model,
    set_user_model,
    format_settings_text,
)
from src.utils.logger import logger


@require_auth
async def callback_open_settings(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Открывает меню настроек (Inline)."""
    query: CallbackQuery = update.callback_query
    await query.answer()
    
    current_model = get_user_model(context)
    
    await query.message.reply_text(
        format_settings_text(context),
        reply_markup=create_settings_inline_keyboard(current_model)
    )


@require_auth
async def callback_toggle_model(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Переключает модель между NBP и Z-Image."""
    query = update.callback_query
    current_model = get_user_model(context)
    
    # Toggle model
    new_model = "nbp" if current_model == "zimage" else "zimage"
    set_user_model(context, new_model)
    
    model_name = MODEL_LABELS.get(new_model, new_model)
    await query.answer(f"Модель изменена на: {model_name}")
    
    await query.message.edit_text(
        format_settings_text(context),
        reply_markup=create_settings_inline_keyboard(new_model)
    )


@require_auth
async def callback_regenerate(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик кнопки 'Регенерировать'."""
    query: CallbackQuery = update.callback_query
    await query.answer("Для регенерации отправьте новый запрос на генерацию")
    
    await query.message.reply_text(
        "🔄 Для регенерации изображения отправьте новый текстовый запрос.\n"
        "Вы можете использовать тот же промпт или изменить его."
    )


@require_auth
async def callback_save(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик кнопки 'Сохранить'."""
    query: CallbackQuery = update.callback_query
    await query.answer("✅ Изображение сохранено в чате. Вы можете скачать его в любое время.")


RESOLUTION_MAP = {
    "1k": RESOLUTION_1K,
    "2k": RESOLUTION_2K,
    "4k": RESOLUTION_4K,
}

ASPECT_MAP = {
    "1_1": ASPECT_RATIO_1_1,
    "16_9": ASPECT_RATIO_16_9,
    "9_16": ASPECT_RATIO_9_16,
    "4_3": ASPECT_RATIO_4_3,
    "3_4": ASPECT_RATIO_3_4,
    "4_5": ASPECT_RATIO_4_5,
    "5_4": ASPECT_RATIO_5_4,
}


@require_auth
async def callback_set_resolution(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Устанавливает предпочитаемое разрешение пользователя."""
    query = update.callback_query
    _, value = query.data.split(":")
    new_resolution = RESOLUTION_MAP.get(value)
    
    if not new_resolution:
        await query.answer("Неизвестное разрешение", show_alert=True)
        return
    
    context.user_data["user_resolution"] = new_resolution
    await query.answer(f"Разрешение: {RESOLUTION_LABELS.get(new_resolution, new_resolution)}")
    
    current_model = get_user_model(context)
    await query.message.edit_text(
        format_settings_text(context),
        reply_markup=create_settings_inline_keyboard(current_model)
    )


@require_auth
async def callback_set_aspect_ratio(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Устанавливает предпочитаемое соотношение сторон пользователя."""
    query = update.callback_query
    _, value = query.data.split(":")
    new_aspect = ASPECT_MAP.get(value)
    
    if not new_aspect:
        await query.answer("Неизвестный формат", show_alert=True)
        return
    
    context.user_data["user_aspect_ratio"] = new_aspect
    await query.answer(f"Формат: {ASPECT_LABELS.get(new_aspect, new_aspect)}")
    
    current_model = get_user_model(context)
    await query.message.edit_text(
        format_settings_text(context),
        reply_markup=create_settings_inline_keyboard(current_model)
    )
