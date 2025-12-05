"""
Seedream 4.0 Client - Бюджетная генерация изображений (ByteDance).
Стоимость: $0.03 за изображение. Поддерживает 4K.
"""
import aiohttp
import base64
from typing import Optional, Dict, Any
from io import BytesIO

from src.config import (
    SEEDREAM_API_KEY,
    SEEDREAM_BASE_URL,
    SEEDREAM_PRICE_PER_IMAGE,
    RESOLUTION_2K,
    RESOLUTION_4K,
    AI_REQUEST_TIMEOUT
)
from src.utils.logger import logger, log_cost_operation
from src.utils.sanitizer import sanitize_prompt, validate_image_request


class SeedreamClient:
    """
    Клиент для Seedream 4.0 (ByteDance) - бюджетная генерация изображений.
    Поддерживает структурированные макеты, редактирование текста, постеры.
    """
    
    def __init__(self):
        self.api_key = SEEDREAM_API_KEY
        self.base_url = SEEDREAM_BASE_URL
        self.price_per_image = SEEDREAM_PRICE_PER_IMAGE
        
        if not self.api_key:
            logger.warning("SEEDREAM_API_KEY not set, Seedream client will not work")
    
    async def generate_image(
        self,
        prompt: str,
        resolution: str = RESOLUTION_2K,
        style: Optional[str] = None,
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Генерация изображения через Seedream 4.0.
        
        Args:
            prompt: Промпт для генерации
            resolution: Разрешение (1024x1024, 2048x2048, 4096x4096)
            style: Стиль изображения (опционально)
            user_id: ID пользователя для логирования
            
        Returns:
            bytes: Изображение в формате PNG, или None при ошибке
        """
        if not self.api_key:
            logger.error("Seedream API key not configured")
            return None
        
        # Санитизация промпта (Seedream имеет меньше цензуры)
        sanitized_prompt, allowed, reason = sanitize_prompt(prompt, model="seedream")
        if not allowed:
            logger.warning(f"Prompt blocked by sanitizer: {reason}")
            return None
        
        # Валидация запроса
        is_valid, error_msg = validate_image_request(sanitized_prompt, resolution)
        if not is_valid:
            logger.warning(f"Invalid image request: {error_msg}")
            return None
        
        try:
            # Подготовка запроса к BytePlus ModelArk API
            # Примечание: Точный формат API может отличаться, требуется документация BytePlus
            payload = {
                "model": "seedream-4.0",  # Уточнить точное имя модели
                "prompt": sanitized_prompt,
                "resolution": resolution,
                "num_images": 1
            }
            
            if style:
                payload["style"] = style
            
            headers = {
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json"
            }
            
            async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=AI_REQUEST_TIMEOUT)) as session:
                async with session.post(
                    f"{self.base_url}/images/generations",
                    json=payload,
                    headers=headers
                ) as response:
                    if response.status == 200:
                        result = await response.json()
                        
                        # Извлекаем изображение из ответа
                        # Формат может быть base64 или URL
                        image_data = result.get("data", [{}])[0]
                        image_url = image_data.get("url")
                        image_b64 = image_data.get("b64_json")
                        
                        if image_b64:
                            # Декодируем base64
                            image_bytes = base64.b64decode(image_b64)
                        elif image_url:
                            # Скачиваем изображение по URL
                            async with session.get(image_url) as img_response:
                                image_bytes = await img_response.read()
                        else:
                            logger.error("No image data in Seedream response")
                            return None
                        
                        # Логируем затраты
                        log_cost_operation(
                            operation="generate",
                            model="seedream",
                            cost=self.price_per_image,
                            user_id=user_id
                        )
                        
                        logger.info(f"Image generated via Seedream: {len(image_bytes)} bytes")
                        return image_bytes
                    else:
                        error_text = await response.text()
                        logger.error(f"Seedream API error {response.status}: {error_text}")
                        return None
        except aiohttp.ClientError as e:
            logger.error(f"Seedream API request failed: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error in Seedream generation: {e}")
            return None
    
    async def edit_image(
        self,
        image: bytes,
        instructions: str,
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Редактирование существующего изображения.
        
        Args:
            image: Исходное изображение
            instructions: Инструкции для редактирования
            user_id: ID пользователя для логирования
            
        Returns:
            bytes: Отредактированное изображение, или None при ошибке
        """
        if not self.api_key:
            logger.error("Seedream API key not configured")
            return None
        
        # Санитизация инструкций
        sanitized_instructions, allowed, reason = sanitize_prompt(instructions, model="seedream")
        if not allowed:
            logger.warning(f"Edit instructions blocked: {reason}")
            return None
        
        try:
            # Подготовка изображения для загрузки
            image_b64 = base64.b64encode(image).decode('utf-8')
            
            payload = {
                "model": "seedream-4.0",
                "image": image_b64,
                "instructions": sanitized_instructions,
                "num_images": 1
            }
            
            headers = {
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json"
            }
            
            async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=AI_REQUEST_TIMEOUT)) as session:
                async with session.post(
                    f"{self.base_url}/images/edits",
                    json=payload,
                    headers=headers
                ) as response:
                    if response.status == 200:
                        result = await response.json()
                        image_data = result.get("data", [{}])[0]
                        image_b64 = image_data.get("b64_json")
                        
                        if image_b64:
                            image_bytes = base64.b64decode(image_b64)
                            
                            log_cost_operation(
                                operation="edit",
                                model="seedream",
                                cost=self.price_per_image,
                                user_id=user_id
                            )
                            
                            logger.info(f"Image edited via Seedream: {len(image_bytes)} bytes")
                            return image_bytes
                        else:
                            logger.error("No image data in Seedream edit response")
                            return None
                    else:
                        error_text = await response.text()
                        logger.error(f"Seedream edit API error {response.status}: {error_text}")
                        return None
        except Exception as e:
            logger.error(f"Seedream edit failed: {e}")
            return None


# Глобальный экземпляр клиента
seedream_client = SeedreamClient()

