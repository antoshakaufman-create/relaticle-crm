"""
Санитизация промптов для безопасности.
Seedream 4.0 имеет меньше цензуры, поэтому требуется
корпоративная проверка на недопустимый контент.
"""
import re
from typing import List, Tuple, Optional

from src.utils.logger import logger


# Список запрещенных ключевых слов (пример, должен быть расширен)
BLOCKED_KEYWORDS: List[str] = [
    # Добавить корпоративные ограничения
    # Пример: "violence", "explicit", и т.д.
]

# Паттерны для подозрительного контента
SUSPICIOUS_PATTERNS: List[re.Pattern] = [
    # Добавить паттерны для обнаружения проблемного контента
    # re.compile(r"pattern", re.IGNORECASE),
]


def sanitize_prompt(prompt: str, model: str = "seedream") -> Tuple[str, bool, Optional[str]]:
    """
    Санитизация промпта для корпоративного использования.
    
    Args:
        prompt: Исходный промпт
        model: Модель, для которой санитизируется промпт (seedream более строгий)
        
    Returns:
        Tuple[str, bool, Optional[str]]: (санитизированный промпт, разрешен ли, причина отказа)
    """
    original_prompt = prompt
    sanitized = prompt.strip()
    
    # Проверка на пустой промпт
    if not sanitized:
        return "", False, "Empty prompt"
    
    # Проверка на запрещенные ключевые слова
    prompt_lower = sanitized.lower()
    for keyword in BLOCKED_KEYWORDS:
        if keyword.lower() in prompt_lower:
            logger.warning(
                f"Blocked prompt contains forbidden keyword: {keyword}. "
                f"User prompt: {prompt[:100]}..."
            )
            return "", False, f"Forbidden keyword detected: {keyword}"
    
    # Проверка на подозрительные паттерны
    for pattern in SUSPICIOUS_PATTERNS:
        if pattern.search(sanitized):
            logger.warning(
                f"Blocked prompt matches suspicious pattern. "
                f"User prompt: {prompt[:100]}..."
            )
            return "", False, "Suspicious content detected"
    
    # Дополнительная проверка для Seedream (меньше цензуры)
    if model == "seedream":
        # Можно добавить дополнительные проверки
        pass
    
    # Если все проверки пройдены
    if sanitized != original_prompt:
        logger.info(f"Prompt sanitized: {original_prompt[:50]}... -> {sanitized[:50]}...")
    
    return sanitized, True, None


def validate_image_request(prompt: str, resolution: str) -> Tuple[bool, Optional[str]]:
    """
    Валидация запроса на генерацию изображения.
    
    Args:
        prompt: Промпт для генерации
        resolution: Разрешение (1K, 2K, 4K)
        
    Returns:
        Tuple[bool, Optional[str]]: (валиден ли запрос, причина отказа)
    """
    # Проверка длины промпта
    if len(prompt) > 2000:
        return False, "Prompt too long (max 2000 characters)"
    
    # Проверка разрешения
    valid_resolutions = ["1024x1024", "2048x2048", "4096x4096"]
    if resolution not in valid_resolutions:
        return False, f"Invalid resolution: {resolution}"
    
    return True, None

