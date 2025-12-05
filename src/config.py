"""
Конфигурация бота.
Все секреты должны быть загружены из переменных окружения.
⚠️ В CI/CD эти переменные должны быть настроены как Protected Variables.
"""
import os
from typing import List
from dotenv import load_dotenv

load_dotenv()

# ============================================================================
# ACL - Access Control List (Критический компонент безопасности)
# ============================================================================
ALLOWED_USER_IDS: List[int] = [
    int(uid.strip())
    for uid in os.getenv("ALLOWED_USER_IDS", "").split(",")
    if uid.strip().isdigit()
]
if not ALLOWED_USER_IDS:
    raise ValueError(
        "ALLOWED_USER_IDS must be set in environment variables. "
        "Format: ALLOWED_USER_IDS=123456789,987654321"
    )

# ============================================================================
# Telegram Bot Configuration
# ============================================================================
TELEGRAM_BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")
if not TELEGRAM_BOT_TOKEN:
    raise ValueError("TELEGRAM_BOT_TOKEN must be set in environment variables")

WEBHOOK_URL = os.getenv("WEBHOOK_URL")
WEBHOOK_SECRET = os.getenv("WEBHOOK_SECRET", "")

# Для CI/CD уведомлений
TELEGRAM_NOTIFICATION_CHAT_ID = os.getenv("TELEGRAM_NOTIFICATION_CHAT_ID")

# ============================================================================
# Gemini 2.5 Flash-Lite Configuration (Free Tier)
# ============================================================================
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")
if not GEMINI_API_KEY:
    raise ValueError("GEMINI_API_KEY must be set in environment variables")

GEMINI_MODEL = "gemini-2.5-flash-lite"

# ⚠️ КРИТИЧЕСКОЕ ПРЕДУПРЕЖДЕНИЕ О КОНФИДЕНЦИАЛЬНОСТИ:
# Free Tier Gemini может использовать контент для улучшения продуктов Google.
# Для корпоративного использования с требованиями конфиденциальности
# необходимо использовать платный план Gemini API.
GEMINI_USE_FREE_TIER = os.getenv("GEMINI_USE_FREE_TIER", "true").lower() == "true"

# ============================================================================
# Seedream 4.0 Configuration (ByteDance) - Бюджетная генерация
# ============================================================================
SEEDREAM_API_KEY = os.getenv("SEEDREAM_API_KEY")
SEEDREAM_BASE_URL = os.getenv(
    "SEEDREAM_BASE_URL",
    "https://ark.cn-beijing.volces.com/api/v3"  # BytePlus ModelArk API
)
SEEDREAM_PRICE_PER_IMAGE = 0.03  # $0.03 за изображение

# ============================================================================
# Replicate Configuration (Z-Image-Turbo)
# ============================================================================
REPLICATE_API_TOKEN = os.getenv("REPLICATE_API_TOKEN")
# Модель Z-Image-Turbo на Replicate
ZIMAGE_MODEL_ID = "prunaai/z-image-turbo:7ea16386290ff5977c7812e66e462d7ec3954d8e007a8cd18ded3e7d41f5d7cf"
ZIMAGE_PRICE_PER_IMAGE = 0.03  # Примерная цена за генерацию

# ============================================================================
# Nano Banana Pro Configuration (Gemini 3 Pro Image)
# ============================================================================
NBP_API_KEY = os.getenv("NBP_API_KEY")
if not NBP_API_KEY:
    raise ValueError("NBP_API_KEY must be set in environment variables")

NBP_MODEL_NAME = "gemini-3-pro-image-preview"  # Nano Banana Pro (4K, premium quality)
NBP_FALLBACK_MODEL_NAME = "gemini-2.5-flash-image" # Fallback model (faster, cheaper)
NBP_BASE_URL = "https://generativelanguage.googleapis.com/v1beta"

# Опциональная интеграция с KIE API для экономии 20%
NBP_USE_KIE_API = os.getenv("NBP_USE_KIE_API", "false").lower() == "true"
NBP_KIE_BASE_URL = os.getenv("NBP_KIE_BASE_URL")
NBP_KIE_API_KEY = os.getenv("NBP_KIE_API_KEY")

# Цены NBP
NBP_PRICE_2K = 0.139
NBP_PRICE_4K = 0.24
NBP_PRICE_4K_KIE = 0.12  # Через KIE API (экономия 20%)

# Разрешения
RESOLUTION_1K = "1024x1024"
RESOLUTION_2K = "2048x2048"
RESOLUTION_4K = "4096x4096"

# Соотношения сторон
ASPECT_RATIO_1_1 = "1:1"
ASPECT_RATIO_16_9 = "16:9"
ASPECT_RATIO_9_16 = "9:16"
ASPECT_RATIO_4_3 = "4:3"
ASPECT_RATIO_3_4 = "3:4"
ASPECT_RATIO_4_5 = "4:5"
ASPECT_RATIO_5_4 = "5:4"


def calculate_resolution(base_resolution: str, aspect_ratio: str) -> str:
    """
    Вычисляет разрешение на основе базового разрешения и соотношения сторон.
    
    Args:
        base_resolution: Базовое разрешение (2048x2048 или 4096x4096)
        aspect_ratio: Соотношение сторон (1:1, 16:9, 9:16, 4:3, 3:4)
        
    Returns:
        Строка разрешения в формате "WIDTHxHEIGHT"
    """
    # Извлекаем базовую ширину
    base_width = int(base_resolution.split("x")[0])
    
    # Парсим соотношение сторон
    ratio_parts = aspect_ratio.split(":")
    if len(ratio_parts) != 2:
        return base_resolution  # Возвращаем базовое разрешение при ошибке
    
    width_ratio = float(ratio_parts[0])
    height_ratio = float(ratio_parts[1])
    
    # Вычисляем размеры
    if width_ratio >= height_ratio:
        # Широкий формат (16:9, 4:3, 1:1)
        width = base_width
        height = int(base_width * height_ratio / width_ratio)
    else:
        # Вертикальный формат (9:16, 3:4)
        height = base_width
        width = int(base_width * width_ratio / height_ratio)
    
    # Округляем до четных чисел (требование для некоторых API)
    width = width if width % 2 == 0 else width + 1
    height = height if height % 2 == 0 else height + 1
    
    return f"{width}x{height}"

# ============================================================================
# Storage Configuration (Временное хранилище медиафайлов)
# ============================================================================
STORAGE_TYPE = os.getenv("STORAGE_TYPE", "local")  # local, ftp, s3, gcs, yandex

# Локальное хранилище
STORAGE_LOCAL_PATH = os.getenv("STORAGE_LOCAL_PATH", "/tmp/telegram_bot_media")

# FTP конфигурация (если используется)
STORAGE_FTP_HOST = os.getenv("STORAGE_FTP_HOST")
STORAGE_FTP_USER = os.getenv("STORAGE_FTP_USER")
STORAGE_FTP_PASSWORD = os.getenv("STORAGE_FTP_PASSWORD")
STORAGE_FTP_PATH = os.getenv("STORAGE_FTP_PATH", "/telegram_bot")

# Облачное хранилище (S3/GCS/Yandex)
STORAGE_BUCKET_NAME = os.getenv("STORAGE_BUCKET_NAME")
STORAGE_ACCESS_KEY = os.getenv("STORAGE_ACCESS_KEY")
STORAGE_SECRET_KEY = os.getenv("STORAGE_SECRET_KEY")
STORAGE_REGION = os.getenv("STORAGE_REGION", "us-east-1")

# ============================================================================
# Application Configuration
# ============================================================================
LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO")
DEBUG = os.getenv("DEBUG", "false").lower() == "true"

# Таймауты (в секундах)
AI_REQUEST_TIMEOUT = int(os.getenv("AI_REQUEST_TIMEOUT", "120"))
MEDIA_DOWNLOAD_TIMEOUT = int(os.getenv("MEDIA_DOWNLOAD_TIMEOUT", "30"))

# Автоматическое удаление временных файлов (в часах)
MEDIA_CLEANUP_HOURS = int(os.getenv("MEDIA_CLEANUP_HOURS", "24"))

