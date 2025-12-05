"""
Base handlers - Базовые обработчики команд и главного меню.
Обработчики, которые не требуют ConversationHandler.
"""
from telegram import Update
from telegram.ext import ContextTypes

from src.middleware import require_auth
from src.cost_manager import cost_manager
from src.keyboards import create_main_menu_keyboard, create_settings_inline_keyboard
from src.handlers.settings_utils import format_settings_text, get_user_model
from src.utils.logger import logger


@require_auth
async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик команды /start с главным меню."""
    user = update.effective_user
    welcome_message = (
        f"Привет, {user.first_name}!\n\n"
        "Я внутрикорпоративный AI-бот для генерации изображений.\n\n"
        "Возможности:\n"
        "• Генерация изображений по текстовому описанию\n"
        "• Редактирование изображений\n"
        "• Обработка голосовых сообщений\n"
        "• Мультимодальные запросы (текст + изображения)\n\n"
        "Используйте кнопки ниже или отправьте текстовое описание!"
    )
    
    keyboard = create_main_menu_keyboard()
    await update.message.reply_text(
        welcome_message,
        reply_markup=keyboard
    )
    logger.info(f"Start command from user {user.id}")


@require_auth
async def stats_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик команды /stats для просмотра статистики затрат."""
    user_id = update.effective_user.id
    
    try:
        stats = cost_manager.get_statistics()
        user_cost = cost_manager.get_user_cost(user_id)
        
        stats_message = (
            f"📊 Статистика затрат:\n\n"
            f"Общая стоимость: ${stats['total_cost']:.4f}\n"
            f"Ваши затраты: ${user_cost:.4f}\n"
            f"Всего операций: {stats['total_operations']}\n"
            f"Пользователей: {stats['users_count']}\n"
            f"Средняя стоимость: ${stats['average_cost_per_operation']:.4f}\n\n"
            f"По моделям: {stats['operations_by_model']}\n"
            f"По типам: {stats['operations_by_type']}"
        )
        
        await update.message.reply_text(stats_message)
    except Exception as e:
        logger.error(f"Error in stats_handler: {e}")
        await update.message.reply_text("❌ Ошибка при получении статистики.")


@require_auth
async def main_menu_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик кнопок главного меню (Reply Keyboard)."""
    user_input = update.message.text
    
    if user_input == "📊 Статистика затрат":
        await stats_handler(update, context)
    elif user_input == "⚙️ Настройки":
        current_model = get_user_model(context)
        await update.message.reply_text(
            format_settings_text(context),
            reply_markup=create_settings_inline_keyboard(current_model)
        )
    else:
        # Неизвестная команда - игнорируем
        pass

