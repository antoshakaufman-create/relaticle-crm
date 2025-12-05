"""
EditFlowHandler - ConversationHandler для редактирования изображений.
Состояния: EDIT_WAITING_INPUT -> EDIT_WAITING_INSTRUCTIONS -> END
"""
from telegram import Update, CallbackQuery
from telegram.ext import (
    ConversationHandler,
    MessageHandler,
    CommandHandler,
    CallbackQueryHandler,
    filters,
    ContextTypes
)
from telegram.constants import ParseMode

from src.middleware import require_auth
from src.media_processor import media_processor
from src.nbp_client import nbp_client
from src.config import RESOLUTION_2K, calculate_resolution
from src.handlers.settings_utils import (
    get_user_resolution,
    get_user_aspect_ratio,
)
from src.keyboards import (
    create_edit_mode_reply_keyboard,
    create_edit_instructions_reply_keyboard,
    create_multimodal_reply_keyboard,
    create_image_actions_keyboard,
    remove_reply_keyboard,
    create_main_menu_keyboard
)
from src.utils.logger import logger

# Константы для ConversationHandler редактирования
EDIT_WAITING_INPUT = 1
EDIT_WAITING_INSTRUCTIONS = 2
END = ConversationHandler.END


@require_auth
async def callback_edit_image(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обработчик кнопки 'Редактировать' - вход в ConversationHandler."""
    query: CallbackQuery = update.callback_query
    await query.answer()
    
    # Сохраняем исходное изображение
    if query.message.document:
        context.user_data["original_image_file_id"] = query.message.document.file_id
        context.user_data["original_image_type"] = "document"
    elif query.message.photo:
        context.user_data["original_image_file_id"] = query.message.photo[-1].file_id
        context.user_data["original_image_type"] = "photo"
    else:
        await query.answer("Ошибка: изображение не найдено", show_alert=True)
        return END
    
    # Скачиваем исходное изображение
    original_image_data = await media_processor.download_file(
        context.user_data["original_image_file_id"],
        update.effective_user.id
    )
    
    if not original_image_data:
        await query.answer("Ошибка загрузки изображения", show_alert=True)
        return END
    
    context.user_data["original_image_data"] = original_image_data
    context.user_data["reference_images"] = []
    
    # Показываем Reply Keyboard для режима редактирования
    reply_keyboard = create_edit_mode_reply_keyboard()
    
    await query.message.reply_text(
        "✏️ **Режим редактирования**\n\n"
        "Используйте кнопки ниже или отправьте:\n"
        "• **Текстовые инструкции** (например: 'измени фон на фиолетовый')\n"
        "• **Референсное изображение** с подписью\n"
        "• **Несколько изображений** (до 14) для сложного редактирования\n\n"
        "Выберите действие:",
        reply_markup=reply_keyboard,
        parse_mode=ParseMode.MARKDOWN
    )
    
    return EDIT_WAITING_INPUT


@require_auth
async def handle_edit_input(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Обрабатывает ввод в режиме редактирования.
    Поддерживает как Reply Keyboard кнопки, так и обычные сообщения.
    """
    message = update.message
    user_id = update.effective_user.id
    text = message.text or ""
    
    # Обработка Reply Keyboard кнопок
    if text == "❌ Отмена":
        return await cancel_edit(update, context)
    elif text == "✅ Завершить редактирование":
        # Пользователь нажал "Применить" без инструкций
        if not context.user_data.get("reference_images"):
            await message.reply_text(
                "⚠️ Отправьте текстовые инструкции или референсное изображение перед применением.",
                reply_markup=create_edit_mode_reply_keyboard()
            )
            return EDIT_WAITING_INPUT
        else:
            # Есть референсы, но нет инструкций - запрашиваем
            await message.reply_text(
                "Отправьте текстовые инструкции для применения изменений:",
                reply_markup=create_edit_instructions_reply_keyboard()
            )
            return EDIT_WAITING_INSTRUCTIONS
    
    # Обработка изображений
    if message.photo or message.document:
        file_id = message.photo[-1].file_id if message.photo else message.document.file_id
        
        image_data = await media_processor.download_file(file_id, user_id)
        if image_data:
            mime_type = "image/jpeg"
            if message.document and message.document.mime_type:
                mime_type = message.document.mime_type
            
            reference_image = {
                "type": "image",
                "data": image_data,
                "mime_type": mime_type
            }
            
            context.user_data["reference_images"].append(reference_image)
            ref_count = len(context.user_data["reference_images"])
            
            if ref_count >= 14:
                # Достигнут лимит
                await message.reply_text(
                    f"✅ Референсных изображений: {ref_count}/14 (лимит достигнут).\n"
                    "Отправьте текстовые инструкции:",
                    reply_markup=create_edit_instructions_reply_keyboard()
                )
                return EDIT_WAITING_INSTRUCTIONS
            elif message.caption:
                # Есть и изображение, и инструкции
                instructions = message.caption
                await process_edit_with_reference(update, context, instructions)
                return END
            else:
                # Только изображение, ждем инструкции или еще изображения
                await message.reply_text(
                    f"✅ Референсное изображение получено ({ref_count}/14).\n\n"
                    "Можете:\n"
                    "• Отправить еще изображение\n"
                    "• Отправить текстовые инструкции для применения",
                    reply_markup=create_multimodal_reply_keyboard()
                )
                return EDIT_WAITING_INPUT
        else:
            await message.reply_text(
                "❌ Не удалось загрузить изображение. Попробуйте еще раз.",
                reply_markup=create_edit_mode_reply_keyboard()
            )
            return EDIT_WAITING_INPUT
    
    # Обработка текстовых инструкций
    elif text and text not in ["📝 Текстовые инструкции", "🖼️ Референсное изображение"]:
        instructions = text
        
        if context.user_data.get("reference_images"):
            await process_edit_with_reference(update, context, instructions)
            return END
        else:
            await process_text_edit(update, context, instructions)
            return END
    
    else:
        # Неизвестная команда или пустое сообщение
        await message.reply_text(
            "Пожалуйста, отправьте текстовые инструкции или изображение.",
            reply_markup=create_edit_mode_reply_keyboard()
        )
        return EDIT_WAITING_INPUT


@require_auth
async def handle_edit_instructions(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обрабатывает текстовые инструкции после загрузки референсных изображений."""
    message = update.message
    text = message.text or ""
    
    # Обработка Reply Keyboard кнопок
    if text == "❌ Отменить":
        return await cancel_edit(update, context)
    elif text == "✅ Применить изменения":
        # Пользователь нажал кнопку, но инструкции уже должны быть в предыдущем сообщении
        # Или запрашиваем инструкции
        if not text or text in ["✅ Применить изменения", "❌ Отменить"]:
            await message.reply_text(
                "Отправьте текстовые инструкции для редактирования:",
                reply_markup=create_edit_instructions_reply_keyboard()
            )
            return EDIT_WAITING_INSTRUCTIONS
    
    instructions = text
    if not instructions or instructions in ["✅ Применить изменения", "❌ Отменить"]:
        await message.reply_text(
            "Пожалуйста, отправьте текстовые инструкции.",
            reply_markup=create_edit_instructions_reply_keyboard()
        )
        return EDIT_WAITING_INSTRUCTIONS
    
    await process_edit_with_reference(update, context, instructions)
    return END


@require_auth
async def cancel_edit(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Отмена редактирования с удалением Reply Keyboard."""
    query = update.callback_query
    message = update.message or (query.message if query else None)
    
    if query:
        await query.answer("Редактирование отменено")
        await query.edit_message_text("❌ Редактирование отменено.")
    elif message:
        await message.reply_text(
            "❌ Редактирование отменено.",
            reply_markup=create_main_menu_keyboard()
        )
    
    # Очищаем контекст
    context.user_data.pop("original_image_data", None)
    context.user_data.pop("reference_images", None)
    context.user_data.pop("original_image_file_id", None)
    context.user_data.pop("state", None)
    
    return END


async def process_text_edit(update: Update, context: ContextTypes.DEFAULT_TYPE, instructions: str):
    """Обрабатывает простое текстовое редактирование без референсов."""
    message = update.message
    user_id = update.effective_user.id
    
    status_msg = await message.reply_text(
        "✏️ Редактирую изображение...\n⏳ Это может занять до 2 минут",
        reply_markup=create_main_menu_keyboard()
    )
    
    original_image = context.user_data.get("original_image_data")
    if not original_image:
        await status_msg.edit_text("❌ Исходное изображение не найдено.")
        return
    
    try:
        # Получаем настройки пользователя
        base_resolution = get_user_resolution(context)
        aspect_ratio = get_user_aspect_ratio(context)
        final_resolution = calculate_resolution(base_resolution, aspect_ratio)

        # Вызываем NBP для редактирования
        edited_image = await nbp_client.edit_image(
            original_image=original_image,
            instructions=instructions,
            reference_images=None,
            resolution=final_resolution,
            aspect_ratio=aspect_ratio,
            user_id=user_id
        )
        
        if edited_image:
            # Создаем клавиатуру действий для отредактированного изображения
            image_keyboard = create_image_actions_keyboard()
            
            # Отправляем как документ для сохранения качества
            await message.reply_document(
                document=edited_image,
                filename="edited_image.png",
                caption=(
                    f"✅ Изображение отредактировано!\n\n"
                    f"📝 Инструкции: _{instructions[:100]}{'...' if len(instructions) > 100 else ''}_"
                ),
                reply_markup=image_keyboard,
                parse_mode=ParseMode.MARKDOWN
            )
            await status_msg.delete()
        else:
            await status_msg.edit_text(
                "❌ Не удалось отредактировать изображение.\n"
                "Попробуйте сформулировать инструкции иначе или используйте новую генерацию."
            )
    except Exception as e:
        logger.error(f"Edit processing error: {e}")
        await status_msg.edit_text(f"❌ Ошибка при редактировании: {str(e)}")
    
    # Очищаем контекст
    context.user_data.pop("original_image_data", None)
    context.user_data.pop("reference_images", None)
    context.user_data.pop("original_image_file_id", None)


async def process_edit_with_reference(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    instructions: str
):
    """Обрабатывает редактирование с референсными изображениями."""
    message = update.message
    user_id = update.effective_user.id
    
    reference_images = context.user_data.get("reference_images", [])
    ref_count = len(reference_images)
    
    status_msg = await message.reply_text(
        f"✏️ Редактирую изображение с {ref_count} референсными изображениями...\n"
        f"⏳ Это может занять до 2 минут",
        reply_markup=create_main_menu_keyboard()
    )
    
    original_image = context.user_data.get("original_image_data")
    
    if not original_image:
        await status_msg.edit_text("❌ Исходное изображение не найдено.")
        return
    
    try:
        # Получаем настройки пользователя
        base_resolution = get_user_resolution(context)
        aspect_ratio = get_user_aspect_ratio(context)
        final_resolution = calculate_resolution(base_resolution, aspect_ratio)

        # Вызываем NBP для редактирования с референсами
        edited_image = await nbp_client.edit_image(
            original_image=original_image,
            instructions=instructions,
            reference_images=reference_images,
            resolution=final_resolution,
            aspect_ratio=aspect_ratio,
            user_id=user_id
        )
        
        if edited_image:
            # Создаем клавиатуру действий
            image_keyboard = create_image_actions_keyboard()
            
            # Отправляем как документ для сохранения качества
            await message.reply_document(
                document=edited_image,
                filename="edited_image.png",
                caption=(
                    f"✅ Изображение отредактировано!\n\n"
                    f"📝 Инструкции: _{instructions[:80]}{'...' if len(instructions) > 80 else ''}_\n"
                    f"🖼️ Референсов использовано: {ref_count}"
                ),
                reply_markup=image_keyboard,
                parse_mode=ParseMode.MARKDOWN
            )
            await status_msg.delete()
        else:
            await status_msg.edit_text(
                "❌ Не удалось отредактировать изображение.\n"
                "Попробуйте:\n"
                "• Сформулировать инструкции иначе\n"
                "• Использовать другие референсные изображения\n"
                "• Начать новую генерацию"
            )
    except Exception as e:
        logger.error(f"Edit with reference error: {e}")
        await status_msg.edit_text(f"❌ Ошибка при редактировании: {str(e)}")
    
    # Очищаем контекст
    context.user_data.pop("original_image_data", None)
    context.user_data.pop("reference_images", None)
    context.user_data.pop("original_image_file_id", None)


def create_edit_flow_handler():
    """Создает ConversationHandler для режима редактирования."""
    return ConversationHandler(
        entry_points=[
            # Вход через InlineKeyboard кнопку "Редактировать"
            CallbackQueryHandler(callback_edit_image, pattern="^edit_image:"),
        ],
        states={
            EDIT_WAITING_INPUT: [
                # Обработка текстовых сообщений (включая Reply Keyboard кнопки)
                MessageHandler(
                    filters.TEXT & ~filters.COMMAND,
                    handle_edit_input
                ),
                # Обработка изображений
                MessageHandler(
                    filters.PHOTO | filters.Document.IMAGE,
                    handle_edit_input
                ),
            ],
            EDIT_WAITING_INSTRUCTIONS: [
                # Обработка текстовых инструкций
                MessageHandler(
                    filters.TEXT & ~filters.COMMAND,
                    handle_edit_instructions
                ),
            ],
        },
        fallbacks=[
            CallbackQueryHandler(cancel_edit, pattern="^cancel_edit$"),
            CommandHandler("cancel", cancel_edit),
            # Обработка Reply Keyboard кнопки "Отменить"
            MessageHandler(
                filters.Regex("^❌ Отмена$"),
                cancel_edit
            ),
        ],
        name="edit_flow",
        persistent=False,
    )

