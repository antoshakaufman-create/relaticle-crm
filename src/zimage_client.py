"""
Z-Image Client - Интеграция с Z-Image Turbo через Replicate.
Обеспечивает быструю генерацию изображений (sub-second latency на GPU провайдера).
"""
import aiohttp
import replicate
import asyncio
from typing import Optional, Dict, Any

from src.config import (
    REPLICATE_API_TOKEN,
    ZIMAGE_MODEL_ID,
    ZIMAGE_PRICE_PER_IMAGE,
    AI_REQUEST_TIMEOUT,
    RESOLUTION_2K
)
from src.utils.logger import logger, log_cost_operation


class ZImageClient:
    """
    Клиент для Z-Image Turbo (через Replicate).
    """
    
    def __init__(self):
        self.api_token = REPLICATE_API_TOKEN
        self.model_id = ZIMAGE_MODEL_ID
        self.price_per_image = ZIMAGE_PRICE_PER_IMAGE
        
        if not self.api_token:
            logger.warning("REPLICATE_API_TOKEN not set, Z-Image client will not work")
            
        # Настройка клиента Replicate
        if self.api_token:
            self.client = replicate.Client(api_token=self.api_token)
    
    async def generate_image(
        self,
        prompt: str,
        resolution: str = RESOLUTION_2K,  # Z-Image лучше работает с 1024x1024, но поддерживаем интерфейс
        aspect_ratio: str = "1:1",
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Генерация изображения через Z-Image Turbo.
        
        Args:
            prompt: Промпт для генерации
            resolution: Разрешение (игнорируется точное значение, адаптируется под модель)
            aspect_ratio: Соотношение сторон (влияет на width/height)
            user_id: ID пользователя для логирования
            
        Returns:
            bytes: Изображение в формате PNG, или None при ошибке
        """
        if not self.api_token:
            logger.error("Replicate API token not configured")
            return None
        
        # Определение размеров на основе aspect_ratio
        # Z-Image Turbo оптимизирован под 1024x1024. Максимальная сторона не должна превышать ~1280.
        width, height = 1024, 1024
        
        if aspect_ratio == "16:9":
            width, height = 1280, 720
        elif aspect_ratio == "9:16":
            width, height = 720, 1280
        elif aspect_ratio == "4:3":
            width, height = 1152, 864
        elif aspect_ratio == "3:4":
            width, height = 864, 1152
        elif aspect_ratio == "4:5":
            width, height = 896, 1120
        elif aspect_ratio == "5:4":
            width, height = 1120, 896
            
        try:
            logger.info(f"Generating Z-Image: {prompt[:50]}... ({width}x{height})")
            
            # Запуск синхронного вызова Replicate в отдельном потоке
            loop = asyncio.get_event_loop()
            
            # Параметры для Z-Image Turbo
            input_params = {
                "prompt": prompt,
                "width": width,
                "height": height,
                "num_inference_steps": 9,     # Оптимально для Turbo (8 NFEs)
                "guidance_scale": 0.0,        # Важно: 0.0 для Turbo
                # "seed": -1                  # Убираем seed=-1, так как Replicate может не поддерживать это значение
            }
            
            output = await loop.run_in_executor(
                None,
                lambda: self.client.run(
                    self.model_id,
                    input=input_params
                )
            )
            
            # Обработка результата (Replicate возвращает список URL или FileOutput)
            image_url = None
            if isinstance(output, list) and len(output) > 0:
                image_url = str(output[0])
            elif output:
                image_url = str(output)
                
            if image_url:
                # Скачиваем изображение
                async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=AI_REQUEST_TIMEOUT)) as session:
                    async with session.get(image_url) as response:
                        if response.status == 200:
                            image_bytes = await response.read()
                            
                            log_cost_operation(
                                operation="generate",
                                model="z-image-turbo",
                                cost=self.price_per_image,
                                user_id=user_id
                            )
                            
                            logger.info(f"Image generated via Z-Image: {len(image_bytes)} bytes")
                            return image_bytes
                        else:
                            error_text = await response.text()
                            logger.error(f"Failed to download image from Replicate: {response.status}. Response: {error_text}")
                            return None
            else:
                logger.error("No output from Replicate Z-Image. Output was empty or None.")
                return None
                
        except replicate.exceptions.ReplicateError as e:
            logger.error(f"Replicate API Error: {e}")
            return None
        except Exception as e:
            logger.exception(f"Z-Image generation failed with unexpected error: {e}")
            return None

# Глобальный экземпляр клиента
zimage_client = ZImageClient()

