"""
ACL Middleware - Критический компонент безопасности.
Проверяет доступ пользователя ДО любых дорогостоящих AI-вызовов.
"""
from functools import wraps
from typing import Callable, Any

from telegram import Update
from telegram.ext import ContextTypes

from src.config import ALLOWED_USER_IDS
from src.utils.logger import logger, log_access_attempt


async def check_access(update: Update) -> bool:
    """
    Проверяет user_id против белого списка разрешенных ID.
    
    Args:
        update: Telegram Update объект
        
    Returns:
        bool: True если доступ разрешен, False если запрещен
    """
    if not update.effective_user:
        logger.warning("Update without effective_user")
        return False
    
    user_id = update.effective_user.id
    username = update.effective_user.username
    
    if user_id not in ALLOWED_USER_IDS:
        log_access_attempt(
            user_id=user_id,
            username=username,
            allowed=False,
            reason="User ID not in whitelist"
        )
        return False
    
    log_access_attempt(
        user_id=user_id,
        username=username,
        allowed=True
    )
    return True


def require_auth(handler: Callable) -> Callable:
    """
    Декоратор для обработчиков, требующий авторизацию.
    Блокирует выполнение обработчика, если пользователь не авторизован.
    
    Args:
        handler: Функция-обработчик
        
    Returns:
        Обернутая функция с проверкой доступа
    """
    @wraps(handler)
    async def wrapper(update: Update, context: ContextTypes.DEFAULT_TYPE) -> Any:
        # Проверка доступа ПЕРЕД выполнением обработчика
        if not await check_access(update):
            if update.message:
                await update.message.reply_text(
                    "Извините, этот бот предназначен только для внутреннего использования компанией."
                )
            return None
        
        # Если доступ разрешен, выполняем обработчик
        return await handler(update, context)
    
    return wrapper


class ACLMiddleware:
    """
    Middleware класс для интеграции с python-telegram-bot.
    Может быть использован для глобальной проверки доступа.
    """
    
    @staticmethod
    async def process_update(update: Update) -> bool:
        """
        Обрабатывает обновление и проверяет доступ.
        Должен быть вызван перед диспетчеризацией к обработчикам.
        
        Args:
            update: Telegram Update объект
            
        Returns:
            bool: True если доступ разрешен, False если запрещен
        """
        return await check_access(update)

