"""
Структурированное логирование для бота.
"""
import logging
import sys
from datetime import datetime
from typing import Optional

from src.config import LOG_LEVEL, DEBUG


def setup_logger(name: str = "telegram_bot") -> logging.Logger:
    """
    Настройка структурированного логгера.
    
    Args:
        name: Имя логгера
        
    Returns:
        Настроенный логгер
    """
    logger = logging.getLogger(name)
    logger.setLevel(getattr(logging, LOG_LEVEL.upper()))
    
    # Обработчик для консоли
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setLevel(logging.DEBUG if DEBUG else logging.INFO)
    
    # Формат логов
    formatter = logging.Formatter(
        "%(asctime)s - %(name)s - %(levelname)s - %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S"
    )
    console_handler.setFormatter(formatter)
    
    # Удаляем существующие обработчики
    logger.handlers.clear()
    logger.addHandler(console_handler)
    
    return logger


# Глобальный логгер
logger = setup_logger()


def log_access_attempt(
    user_id: int,
    username: Optional[str],
    allowed: bool,
    reason: Optional[str] = None
):
    """
    Логирование попыток доступа для аудита безопасности.
    
    Args:
        user_id: Telegram user ID
        username: Telegram username (опционально)
        allowed: Разрешен ли доступ
        reason: Причина отказа (если доступ запрещен)
    """
    status = "ALLOWED" if allowed else "DENIED"
    log_msg = f"ACCESS {status} - user_id: {user_id}"
    
    if username:
        log_msg += f", username: @{username}"
    
    if reason:
        log_msg += f", reason: {reason}"
    
    if allowed:
        logger.info(log_msg)
    else:
        logger.warning(log_msg)


def log_cost_operation(
    operation: str,
    model: str,
    cost: float,
    user_id: Optional[int] = None
):
    """
    Логирование операций с затратами для контроля бюджета.
    
    Args:
        operation: Тип операции (generate, preview, final)
        model: Использованная модель (seedream, nbp, gemini)
        cost: Стоимость операции в USD
        user_id: ID пользователя (опционально)
    """
    log_msg = f"COST - operation: {operation}, model: {model}, cost: ${cost:.4f}"
    
    if user_id:
        log_msg += f", user_id: {user_id}"
    
    logger.info(log_msg)

