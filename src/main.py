"""
Main - Точка входа приложения.
Инициализация бота, настройка webhook, регистрация обработчиков.
"""
import asyncio
import logging
import os
from aiohttp import web
from telegram import Update
from telegram.ext import (
    Application,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    filters,
    ContextTypes
)

from src.config import (
    TELEGRAM_BOT_TOKEN,
    WEBHOOK_URL,
    WEBHOOK_SECRET,
    LOG_LEVEL,
    DEBUG
)
from src.middleware import ACLMiddleware
from src.handlers import (
    start_handler,
    stats_handler,
    main_menu_handler,
    callback_set_resolution,
    callback_set_aspect_ratio,
    callback_open_settings,
    callback_toggle_model,
    create_generation_flow_handler,
    create_edit_flow_handler
)
from src.utils.logger import logger, setup_logger
from src.utils.storage import storage_manager


# Настройка логирования
setup_logger()
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=getattr(logging, LOG_LEVEL.upper())
)


async def error_handler(update: object, context: ContextTypes.DEFAULT_TYPE):
    """Глобальный обработчик ошибок."""
    logger.error(f"Exception while handling an update: {context.error}", exc_info=context.error)


async def post_init(app: Application):
    """Инициализация после создания приложения."""
    # Настройка webhook для production
    if WEBHOOK_URL:
        webhook_url = f"{WEBHOOK_URL}/webhook"
        await app.bot.set_webhook(
            url=webhook_url,
            secret_token=WEBHOOK_SECRET if WEBHOOK_SECRET else None
        )
        logger.info(f"Webhook set to: {webhook_url}")
    else:
        logger.warning("WEBHOOK_URL not set, using polling mode")
        # Запускаем polling в фоне
        await app.initialize()
        await app.start()
        await app.updater.start_polling()


def create_webhook_handler(app: Application):
    """Создает обработчик webhook с привязкой к приложению."""
    async def webhook_handler(request):
        """Обработчик webhook запросов от Telegram."""
        if WEBHOOK_SECRET:
            # Проверка secret token
            token = request.headers.get("X-Telegram-Bot-Api-Secret-Token")
            if token != WEBHOOK_SECRET:
                logger.warning(f"Invalid webhook secret token")
                return web.Response(status=403)
        
        try:
            data = await request.json()
            update = Update.de_json(data, app.bot)
            
            # Проверка ACL через middleware
            if not await ACLMiddleware.process_update(update):
                logger.warning(f"Access denied for update {update.update_id}")
                return web.Response(status=200)  # Возвращаем 200 чтобы Telegram не повторял запрос
            
            # Обработка обновления
            await app.process_update(update)
            return web.Response(status=200)
        except Exception as e:
            logger.error(f"Error processing webhook: {e}", exc_info=True)
            return web.Response(status=500)
    
    return webhook_handler


async def health_check(request):
    """Health check endpoint для мониторинга."""
    return web.Response(text="OK", status=200)


async def ping(request):
    """Ping endpoint для поддержания активности (предотвращение "засыпания")."""
    return web.Response(text="pong", status=200)


def create_application() -> Application:
    """Создает и настраивает приложение бота."""
    # Создаем приложение
    app = Application.builder().token(TELEGRAM_BOT_TOKEN).build()
    
    # Регистрируем обработчики команд
    app.add_handler(CommandHandler("start", start_handler))
    app.add_handler(CommandHandler("stats", stats_handler))
    
    # Регистрируем ConversationHandler для генерации (первым, до других обработчиков)
    app.add_handler(create_generation_flow_handler())
    
    # Регистрируем ConversationHandler для редактирования (должен быть перед другими обработчиками)
    app.add_handler(create_edit_flow_handler())
    
    # Регистрируем обработчик главного меню (Reply Keyboard кнопки)
    app.add_handler(MessageHandler(
        filters.Regex("^(⚙️ Настройки)$"),
        main_menu_handler
    ))
    
    # Регистрируем обработчики CallbackQuery для действий (не требующих ConversationHandler)
    app.add_handler(CallbackQueryHandler(
        callback_open_settings,
        pattern="^open_settings$"
    ))
    app.add_handler(CallbackQueryHandler(
        callback_set_resolution,
        pattern="^set_res:"
    ))
    app.add_handler(CallbackQueryHandler(
        callback_set_aspect_ratio,
        pattern="^set_aspect:"
    ))
    app.add_handler(CallbackQueryHandler(
        callback_toggle_model,
        pattern="^settings_model_switch$"
    ))
    # callback_edit_image обрабатывается через EditFlowHandler (entry point внутри ConversationHandler)
    
    # Глобальный обработчик ошибок
    app.add_error_handler(error_handler)
    
    return app


async def periodic_cleanup():
    """Периодическая очистка старых файлов."""
    while True:
        try:
            await asyncio.sleep(3600)  # Каждый час
            await storage_manager.cleanup_old_files()
        except Exception as e:
            logger.error(f"Periodic cleanup failed: {e}")


def run_polling():
    """Запуск бота в режиме polling (для разработки)."""
    logger.info("Starting bot in polling mode...")
    app = create_application()
    
    async def post_start(app: Application):
        # Принудительно удаляем webhook перед polling для устранения конфликтов
        try:
            await app.bot.delete_webhook(drop_pending_updates=True)
            logger.info("✅ Webhook deleted, pending updates dropped")
        except Exception as e:
            logger.warning(f"Failed to delete webhook: {e}")
        
        # Запускаем периодическую очистку
        asyncio.create_task(periodic_cleanup())
    
    # Используем post_init callback
    app.post_init = post_start
    
    # drop_pending_updates=True для очистки очереди обновлений
    app.run_polling(allowed_updates=Update.ALL_TYPES, drop_pending_updates=True)


def run_webhook():
    """Запуск бота в режиме webhook (для production на Render/PaaS)."""
    # Динамический порт от Render (или 8000 по умолчанию для локального запуска)
    PORT = int(os.environ.get("PORT", 8000))
    
    logger.info(f"Starting bot in webhook mode on port {PORT}...")
    app = create_application()
    
    async def init_and_run():
        await app.initialize()
        await app.start()
        await post_init(app)
        
        # Создаем aiohttp приложение для webhook
        web_app = web.Application()
        web_app.router.add_post("/webhook", create_webhook_handler(app))
        web_app.router.add_get("/health", health_check)
        web_app.router.add_get("/ping", ping)
        web_app.router.add_get("/", health_check)  # Root для проверки Render
        
        # Запускаем периодическую очистку
        asyncio.create_task(periodic_cleanup())
        
        # Запускаем web сервер на динамическом порту
        runner = web.AppRunner(web_app)
        await runner.setup()
        site = web.TCPSite(runner, "0.0.0.0", PORT)
        await site.start()
        
        logger.info(f"✅ Webhook server started on 0.0.0.0:{PORT}")
        logger.info(f"📡 Webhook URL: {WEBHOOK_URL}/webhook")
        
        # Держим приложение запущенным
        try:
            await asyncio.Future()  # Бесконечное ожидание
        except KeyboardInterrupt:
            logger.info("Shutting down...")
        finally:
            await site.stop()
            await runner.cleanup()
            await app.stop()
            await app.shutdown()
    
    asyncio.run(init_and_run())


if __name__ == "__main__":
    # Определяем режим работы
    if WEBHOOK_URL:
        run_webhook()
    else:
        run_polling()

