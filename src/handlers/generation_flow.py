"""
GenerationFlowHandler - ConversationHandler для основной генерации изображений.
Состояния: ENTRY_PROMPT -> WAITING_RESOLUTION -> PROCESSING -> END
"""
from telegram import Update
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
from src.nbp_client import nbp_client
from src.zimage_client import zimage_client
from src.media_processor import media_processor
from src.prompt_optimizer import prompt_optimizer
from src.config import (
    RESOLUTION_2K,
    RESOLUTION_4K,
    ASPECT_RATIO_1_1,
    ASPECT_RATIO_16_9,
    ASPECT_RATIO_9_16,
    ASPECT_RATIO_4_3,
    ASPECT_RATIO_3_4,
    ASPECT_RATIO_4_5,
    ASPECT_RATIO_5_4,
    calculate_resolution,
    REPLICATE_API_TOKEN
)
from src.keyboards import (
    create_image_actions_keyboard,
    create_multimodal_reply_keyboard,
    create_optimize_prompt_inline_keyboard,
    remove_reply_keyboard,
    create_main_menu_keyboard
)
from src.handlers.settings_utils import (
    RESOLUTION_LABELS,
    ASPECT_LABELS,
    get_user_resolution,
    get_user_aspect_ratio,
    get_user_model,
)
from src.utils.logger import logger

# Состояния для GenerationFlowHandler
ENTRY_PROMPT = 1
COLLECTING_IMAGES = 2  # Сбор референсов
WAITING_OPTIMIZATION = 3
END = ConversationHandler.END


@require_auth
async def entry_prompt_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Entry point: Обработка входящего промпта (текст, голос, фото).
    - Текст/голос: оптимизация и переход к выбору разрешения
    - Фото: переход в режим сбора изображений (multimodal)
    - Фото + подпись: использовать подпись как промпт
    """
    message = update.message
    user_id = update.effective_user.id
    
    # Инициализация списка изображений если еще не инициализирован
    if "multimodal_inputs" not in context.user_data:
        context.user_data["multimodal_inputs"] = []
    
    # Обработка голосовых сообщений
    if message.voice:
        logger.info(f"Processing voice message from user {user_id}")
        await message.reply_text("🎤 Обрабатываю голосовое сообщение...")
        
        voice_file = await media_processor.download_file(
            message.voice.file_id,
            user_id
        )
        
        if not voice_file:
            await message.reply_text("❌ Не удалось обработать голосовое сообщение.")
            return END
        
        transcription = await media_processor.transcribe_voice(voice_file, user_id)
        
        if not transcription:
            await message.reply_text("❌ Не удалось распознать голосовое сообщение.")
            return END
        
        await message.reply_text(f"📝 Распознанный текст: {transcription}")
        user_input = transcription
        return await _process_text_prompt(update, context, user_input)
    
    # Обработка фото (с подписью или без)
    if message.photo or (message.document and message.document.mime_type and message.document.mime_type.startswith("image/")):
        # Скачиваем изображение
        if message.photo:
            file_id = message.photo[-1].file_id  # Берем наибольшее разрешение
            mime_type = "image/jpeg"
        else:
            file_id = message.document.file_id
            mime_type = message.document.mime_type
        
        image_data = await media_processor.download_file(file_id, user_id)
        
        if image_data:
            context.user_data["multimodal_inputs"].append({
                "type": "image",
                "data": image_data,
                "mime_type": mime_type
            })
            img_count = len(context.user_data["multimodal_inputs"])
            
            # Если есть подпись - используем её как промпт
            if message.caption:
                context.user_data["original_prompt"] = message.caption
                await message.reply_text(
                    f"📷 Изображение получено ({img_count}/14)\n"
                    f"📝 Промпт: _{message.caption}_\n\n"
                    f"Хотите добавить еще изображений или продолжить?",
                    reply_markup=create_multimodal_reply_keyboard(),
                    parse_mode=ParseMode.MARKDOWN
                )
                return COLLECTING_IMAGES
            else:
                # Нет подписи - спрашиваем что делать
                await message.reply_text(
                    f"📷 Изображение получено ({img_count}/14)\n\n"
                    f"Вы можете:\n"
                    f"• Отправить еще изображения (до 14)\n"
                    f"• Отправить текстовый промпт для старта\n"
                    f"• Нажать 'Завершить (без текста)'",
                    reply_markup=create_multimodal_reply_keyboard()
                )
                return COLLECTING_IMAGES
        else:
            await message.reply_text("❌ Не удалось загрузить изображение. Попробуйте еще раз.")
            return END
    
    # Обработка текста
    user_input = message.text or ""
    if not user_input:
        await message.reply_text("Пожалуйста, отправьте текстовое описание, голосовое сообщение или изображение.")
        return END
    
    return await _process_text_prompt(update, context, user_input)


async def _process_text_prompt(update: Update, context: ContextTypes.DEFAULT_TYPE, user_input: str):
    """
    Сохраняет промпт и предлагает пользователю опциональную оптимизацию.
    """
    message = update.message
    cleaned_prompt = (user_input or "").strip()
    
    if not cleaned_prompt:
        await message.reply_text("Пожалуйста, отправьте текстовое описание.")
        return END
    
    context.user_data["pending_prompt_candidate"] = cleaned_prompt
    context.user_data["original_prompt"] = cleaned_prompt
    
    img_count = len(context.user_data.get("multimodal_inputs", []))
    multimodal_note = ""
    if img_count:
        multimodal_note = f"\n🖼️ Референсных изображений: {img_count}"
    
    await message.reply_text(
        "Хотите оптимизировать промпт перед генерацией?\n"
        "Оптимизация через Gemini 2.5 Flash улучшает формулировку и занимает ≈2 секунды."
        f"{multimodal_note}",
        reply_markup=create_optimize_prompt_inline_keyboard()
    )
    
    return WAITING_OPTIMIZATION


@require_auth
async def optimize_choice_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Обрабатывает выбор пользователя по оптимизации промпта."""
    query = update.callback_query
    await query.answer()
    
    candidate = context.user_data.get("pending_prompt_candidate")
    if not candidate:
        await query.edit_message_text("❌ Промпт не найден. Отправьте текст заново.")
        return END
    
    if query.data == "optimize_yes":
        await query.edit_message_text("🤖 Оптимизирую промпт...")
        
        # Получаем выбранную модель для оптимизации под неё
        user_model = get_user_model(context)
        optimized = await prompt_optimizer.optimize_prompt(candidate, target_model=user_model)
        
        context.user_data["pending_prompt"] = optimized.get("optimized_prompt", candidate)
        context.user_data["optimization_meta"] = optimized
        await query.message.reply_text("✅ Промпт оптимизирован. Начинаю генерацию...")
    else:
        await query.edit_message_text("⏭ Оптимизацию пропускаем. Начинаю генерацию...")
        context.user_data["pending_prompt"] = candidate
    
    return await _start_generation(query.message, context)


async def _start_generation(message, context: ContextTypes.DEFAULT_TYPE):
    """Запускает генерацию с текущими настройками пользователя."""
    prompt = context.user_data.get("pending_prompt")
    if not prompt:
        await message.reply_text("❌ Промпт отсутствует. Отправьте текст заново.")
        return END
    
    base_resolution = get_user_resolution(context)
    aspect_ratio = get_user_aspect_ratio(context)
    final_resolution = calculate_resolution(base_resolution, aspect_ratio)
    
    aspect_label = ASPECT_LABELS.get(aspect_ratio, aspect_ratio)
    resolution_label = RESOLUTION_LABELS.get(base_resolution, base_resolution)
    
    await message.reply_text(
        "🎨 Генерирую изображение...\n"
        f"• Разрешение: {resolution_label}\n"
        f"• Формат: {aspect_label}\n"
        f"• Итог: {final_resolution}\n\n"
        "⏳ Это может занять до 2 минут.",
        reply_markup=create_main_menu_keyboard()
    )
    
    multimodal_inputs = context.user_data.get("multimodal_inputs") or None
    user_id = message.from_user.id if message.from_user else None
    
    # Получаем выбранную пользователем модель
    user_model = get_user_model(context)
    
    # Автоматическое переключение на NBP для мультимодальных запросов (если выбран Z-Image)
    # Z-Image пока не поддерживает image-to-image через используемый API
    if multimodal_inputs and user_model == "zimage":
        user_model = "nbp"
        await message.reply_text("⚠️ Для обработки изображений (multimodal) переключаюсь на Gemini 3 Pro (NBP).")

    image = None
    model_used = "nbp"
    
    # Логика выбора модели:
    # 1. Если модель Z-Image и есть токен -> используем Z-Image
    # 2. Иначе -> используем NBP
    
    if user_model == "zimage" and REPLICATE_API_TOKEN:
        logger.info("Using Z-Image (via Replicate) as requested by user")
        model_used = "zimage"
        image = await zimage_client.generate_image(
            prompt=prompt,
            resolution=final_resolution,
            aspect_ratio=aspect_ratio,
            user_id=user_id
        )
        
        # Fallback на NBP если Z-Image не сработал
        if not image:
            logger.warning("Z-Image generation failed, falling back to NBP")
            model_used = "nbp"
            image = await nbp_client.generate_image(
                prompt=prompt,
                resolution=final_resolution,
                aspect_ratio=aspect_ratio,
                multimodal_inputs=multimodal_inputs,
                user_id=user_id
            )
    else:
        logger.info(f"Using NBP (Gemini) as requested ({user_model}) or due to missing token")
        model_used = "nbp"
        image = await nbp_client.generate_image(
            prompt=prompt,
            resolution=final_resolution,
            aspect_ratio=aspect_ratio,
            multimodal_inputs=multimodal_inputs,
            user_id=user_id
        )
    
    if image:
        caption_text = (
            "✅ Изображение сгенерировано!\n"
            f"📐 Разрешение: {final_resolution}\n"
            f"🖼️ Соотношение сторон: {aspect_label}\n"
        )
        
        if model_used == "zimage":
            caption_text += "🚀 Модель: Z-Image Turbo\n"
        else:
            caption_text += "✨ Модель: Gemini 3 Pro\n"
            
        caption_text += "\nИзменить параметры можно через кнопку «⚙️ Настройки»."
        
        image_keyboard = create_image_actions_keyboard()
        await message.reply_document(
            document=image,
            filename="generated_image.png",
            caption=caption_text,
            reply_markup=image_keyboard
        )
    else:
        await message.reply_text(
            "❌ Не удалось сгенерировать изображение. Попробуйте изменить промпт или параметры."
        )
    
    _cleanup_generation_context(context)
    return END


def _cleanup_generation_context(context: ContextTypes.DEFAULT_TYPE):
    """Удаляет временные данные после генерации."""
    for key in [
        "pending_prompt",
        "pending_prompt_candidate",
        "optimization_meta",
        "original_prompt",
        "selected_base_resolution",
        "selected_aspect_ratio",
    ]:
        context.user_data.pop(key, None)
    
    context.user_data["multimodal_inputs"] = []


@require_auth
async def collecting_images_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Состояние COLLECTING_IMAGES: Сбор дополнительных изображений для multimodal генерации.
    """
    message = update.message
    user_id = update.effective_user.id
    text = message.text or ""
    
    # Обработка кнопок Reply Keyboard
    if text == "❌ Отмена":
        return await cancel_generation(update, context)
    
    if text == "✅ Завершить (без текста)":
        # Логика обработана ниже, пропускаем здесь
        pass
    
    if text == "➕ Добавить еще изображение":
        # Эта кнопка удалена из клавиатуры, но оставим логику для обратной совместимости
        img_count = len(context.user_data.get("multimodal_inputs", []))
        if img_count >= 14:
            await message.reply_text(
                "⚠️ Достигнут лимит в 14 изображений.\n"
                "Отправьте текстовый промпт или нажмите 'Завершить'.",
                reply_markup=create_multimodal_reply_keyboard()
            )
        else:
            await message.reply_text(
                f"📷 Отправьте изображение ({img_count}/14)",
                reply_markup=create_multimodal_reply_keyboard()
            )
        return COLLECTING_IMAGES
    
    # Обработка изображений
    if message.photo or (message.document and message.document.mime_type and message.document.mime_type.startswith("image/")):
        img_count = len(context.user_data.get("multimodal_inputs", []))
        
        if img_count >= 14:
            await message.reply_text(
                "⚠️ Достигнут лимит в 14 изображений.\n"
                "Отправьте текстовый промпт или нажмите 'Завершить'.",
                reply_markup=create_multimodal_reply_keyboard()
            )
            return COLLECTING_IMAGES
        
        # Скачиваем изображение
        if message.photo:
            file_id = message.photo[-1].file_id
            mime_type = "image/jpeg"
        else:
            file_id = message.document.file_id
            mime_type = message.document.mime_type
        
        image_data = await media_processor.download_file(file_id, user_id)
        
        if image_data:
            context.user_data["multimodal_inputs"].append({
                "type": "image",
                "data": image_data,
                "mime_type": mime_type
            })
            new_count = len(context.user_data["multimodal_inputs"])
            
            # Если есть подпись - сохраняем как промпт
            if message.caption:
                context.user_data["original_prompt"] = message.caption
            
            await message.reply_text(
                f"✅ Изображение добавлено ({new_count}/14)\n\n"
                f"{'📝 Промпт: _' + context.user_data.get('original_prompt', 'не задан')[:50] + '_' if context.user_data.get('original_prompt') else 'Отправьте текстовый промпт для старта или еще фото'}",
                reply_markup=create_multimodal_reply_keyboard(),
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await message.reply_text(
                "❌ Не удалось загрузить изображение. Попробуйте еще раз.",
                reply_markup=create_multimodal_reply_keyboard()
            )
        return COLLECTING_IMAGES
    
    # Обработка текста как промпта
    if text and text not in ["➕ Добавить еще изображение", "✅ Завершить (без текста)", "❌ Отмена"]:
        context.user_data["original_prompt"] = text
        
        # Переходим к оптимизации и выбору разрешения
        return await _process_text_prompt(update, context, text)
    
    if text == "✅ Завершить (без текста)":
        # Проверяем есть ли промпт или картинки
        if not context.user_data.get("original_prompt") and not context.user_data.get("multimodal_inputs"):
            await message.reply_text(
                "⚠️ Пожалуйста, отправьте хотя бы одно изображение или текстовый промпт.",
                reply_markup=create_multimodal_reply_keyboard()
            )
            return COLLECTING_IMAGES
        
        # Если промпта нет, используем заглушку (для генерации чисто по картинке)
        prompt = context.user_data.get("original_prompt") or "Describe this image and generate a similar one in high quality"
        return await _process_text_prompt(update, context, prompt)


@require_auth
async def waiting_resolution_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Устаревший обработчик (оставлен для совместимости).
    Сообщает пользователю о новом механизме настроек.
    """
    await update.message.reply_text(
        "⚙️ Выбор разрешения и формата выполняется через кнопку «⚙️ Настройки».\n"
        "По умолчанию используется профиль 2K • 1:1."
    )
    return END

@require_auth
async def cancel_generation(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Fallback: Отмена генерации."""
    message = update.message or (update.callback_query.message if update.callback_query else None)
    
    if message:
        await message.reply_text(
            "❌ Генерация отменена.",
            reply_markup=create_main_menu_keyboard()
        )
    
    # Очищаем контекст
    for key in [
        "pending_prompt",
        "pending_prompt_candidate",
        "original_prompt",
        "optimization_meta",
        "selected_base_resolution",
        "selected_aspect_ratio",
    ]:
        context.user_data.pop(key, None)
    
    context.user_data["multimodal_inputs"] = []
    
    return END


def create_generation_flow_handler():
    """
    Создает ConversationHandler для основного потока генерации.
    
    Состояния:
    - ENTRY_PROMPT: Получение промпта (текст, голос, фото)
    - COLLECTING_IMAGES: Сбор дополнительных изображений для multimodal
    - WAITING_OPTIMIZATION: Вопрос об оптимизации промпта
    """
    return ConversationHandler(
        entry_points=[
            # Вход через текстовое сообщение (не команда)
            MessageHandler(
                filters.TEXT & ~filters.COMMAND & ~filters.Regex("^(⚙️ Настройки)$"),
                entry_prompt_handler
            ),
            # Вход через голосовое сообщение
            MessageHandler(
                filters.VOICE,
                entry_prompt_handler
            ),
            # Вход через фото (с подписью или без)
            MessageHandler(
                filters.PHOTO,
                entry_prompt_handler
            ),
            # Вход через документ-изображение
            MessageHandler(
                filters.Document.IMAGE,
                entry_prompt_handler
            ),
        ],
        states={
            COLLECTING_IMAGES: [
                # Сбор дополнительных изображений
                MessageHandler(
                    filters.PHOTO | filters.Document.IMAGE,
                    collecting_images_handler
                ),
                # Обработка текстовых команд и промптов
                MessageHandler(
                    filters.TEXT & ~filters.COMMAND,
                    collecting_images_handler
                ),
            ],
            WAITING_OPTIMIZATION: [
                CallbackQueryHandler(
                    optimize_choice_handler,
                    pattern="^optimize_(yes|no)$"
                )
            ],
        },
        fallbacks=[
            CommandHandler("cancel", cancel_generation),
            MessageHandler(
                filters.Regex("^❌ Отмена$"),
                cancel_generation
            ),
            MessageHandler(
                filters.Regex("^❌ Отменить$"),
                cancel_generation
            ),
        ],
        name="generation_flow",
        persistent=False,
    )
