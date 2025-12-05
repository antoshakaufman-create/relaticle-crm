"""
Nano Banana Pro Client - Премиум генерация изображений (Gemini 3 Pro Image).
Поддерживает мультимодальный ввод до 14 элементов, высокое качество логики/математики.
"""
import aiohttp
import base64
import json
import asyncio
from typing import Optional, List, Dict, Any
from io import BytesIO

from src.config import (
    NBP_API_KEY,
    NBP_MODEL_NAME,
    NBP_FALLBACK_MODEL_NAME,
    NBP_BASE_URL,
    NBP_USE_KIE_API,
    NBP_KIE_BASE_URL,
    NBP_KIE_API_KEY,
    NBP_PRICE_2K,
    NBP_PRICE_4K,
    NBP_PRICE_4K_KIE,
    RESOLUTION_2K,
    RESOLUTION_4K,
    AI_REQUEST_TIMEOUT
)
from src.utils.logger import logger, log_cost_operation


class NBPClient:
    """
    Клиент для Nano Banana Pro (Gemini 3 Pro Image).
    Премиум качество для сложных задач: логика, математика, инфографика, точный текст.
    """
    
    def __init__(self):
        self.api_key = NBP_API_KEY
        self.model_name = NBP_MODEL_NAME
        self.fallback_model_name = NBP_FALLBACK_MODEL_NAME
        
        # Выбор API endpoint (KIE для экономии или официальный)
        if NBP_USE_KIE_API and NBP_KIE_BASE_URL and NBP_KIE_API_KEY:
            self.base_url = NBP_KIE_BASE_URL
            self.api_key = NBP_KIE_API_KEY
            self.price_4k = NBP_PRICE_4K_KIE  # $0.12 вместо $0.24
            logger.info("Using KIE API for NBP (20% cost savings)")
        else:
            self.base_url = NBP_BASE_URL
            self.price_4k = NBP_PRICE_4K  # $0.24
            logger.info("Using official NBP API")
        
        self.price_2k = NBP_PRICE_2K
        
        if not self.api_key:
            logger.warning("NBP API key not set, NBP client will not work")
    
    async def generate_image(
        self,
        prompt: str,
        resolution: str = RESOLUTION_2K,
        aspect_ratio: str = "1:1",
        multimodal_inputs: Optional[List[Dict[str, Any]]] = None,
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Генерация изображения через Nano Banana Pro с полной конфигурацией.
        
        Args:
            prompt: Промпт для генерации
            resolution: Разрешение (2048x2048, 4096x4096)
            aspect_ratio: Соотношение сторон (1:1, 16:9, etc.)
            multimodal_inputs: Список мультимодальных входов (до 14 элементов)
            user_id: ID пользователя для логирования
            
        Returns:
            bytes: Изображение в формате PNG, или None при ошибке
        """
        return await self.generate_with_fallback(
            prompt, resolution, aspect_ratio, multimodal_inputs, user_id
        )

    async def generate_with_fallback(
        self,
        prompt: str,
        resolution: str,
        aspect_ratio: str,
        multimodal_inputs: Optional[List[Dict[str, Any]]] = None,
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Генерация с механизмом повторных попыток и fallback.
        """
        # 1. Попытка через основной канал (NBP) с повторами
        try:
            image = await self._generate_with_retry(
                prompt, resolution, aspect_ratio, multimodal_inputs,
                model=self.model_name,
                retries=3
            )
            if image:
                # Успешная генерация через NBP
                cost = self.price_4k if resolution == RESOLUTION_4K else self.price_2k
                log_cost_operation("generate", "nbp", cost, user_id)
                logger.info(f"Image generated via NBP ({resolution}, {aspect_ratio})")
                return image
        except Exception as e:
            logger.error(f"NBP Primary failed: {e}")

        # 2. Fallback на Flash модель
        logger.warning(f"Switching to fallback model: {self.fallback_model_name}")
        try:
            # Для Flash модели могут быть ограничения по разрешению/формату,
            # но мы передаем запрос как есть, надеясь на лучшее
            image = await self._generate_with_retry(
                prompt, resolution, aspect_ratio, multimodal_inputs,
                model=self.fallback_model_name,
                retries=2
            )
            if image:
                # Успешная генерация через Fallback
                cost = 0.039 # Примерная стоимость Flash Image
                log_cost_operation("generate", "gemini-flash", cost, user_id)
                logger.info(f"Image generated via Fallback ({resolution}, {aspect_ratio})")
                return image
        except Exception as e:
            logger.error(f"Fallback failed: {e}")
        
        return None

    async def _generate_with_retry(
        self,
        prompt: str,
        resolution: str,
        aspect_ratio: str,
        multimodal_inputs: Optional[List[Dict[str, Any]]],
        model: str,
        retries: int = 3
    ) -> Optional[bytes]:
        """Внутренний метод генерации с повторами."""
        if not self.api_key:
            return None

        # Определение размеров для конфигурации
        width = int(resolution.split("x")[0])
        height = int(resolution.split("x")[1])
        # Упрощаем разрешение для API: "1K", "2K", "4K"
        # Gemini API может требовать строку "2048x2048" или enum.
        # В документации SDK это image_size="4K".
        # Но в JSON REST API это поле может быть строкой.
        # Попробуем передать наиболее близкое значение.
        image_size_str = "4K" if "4096" in resolution else "2K"

        payload = await self._prepare_gemini_request(
            prompt, image_size_str, aspect_ratio, multimodal_inputs
        )
        endpoint = f"{self.base_url}/models/{model}:generateContent"
        
        headers = {
            "x-goog-api-key": self.api_key,
            "Content-Type": "application/json"
        }

        for attempt in range(retries):
            try:
                # Логируем запрос (без base64 данных для читаемости)
                log_payload = payload.copy()
                if "contents" in log_payload:
                    log_contents = []
                    for content in log_payload["contents"]:
                        log_parts = []
                        for part in content.get("parts", []):
                            if "inline_data" in part:
                                log_parts.append({"inline_data": "<base64_hidden>"})
                            else:
                                log_parts.append(part)
                        log_contents.append({"parts": log_parts})
                    log_payload["contents"] = log_contents
                
                logger.debug(f"API Request (Attempt {attempt+1}/{retries}): {json.dumps(log_payload, ensure_ascii=False)}")

                async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=AI_REQUEST_TIMEOUT)) as session:
                    async with session.post(endpoint, json=payload, headers=headers) as response:
                        if response.status == 200:
                            result = await response.json()
                            return await self._extract_image(result)
                        elif response.status == 429 or response.status >= 500:
                            # Rate limit or Server Error -> Retry
                            error_text = await response.text()
                            logger.warning(f"API Error {response.status}: {error_text}. Retrying...")
                            await asyncio.sleep(2 ** attempt) # Exponential backoff
                        else:
                            # Client Error (400, 401, etc) -> Stop
                            error_text = await response.text()
                            logger.error(f"API Client Error {response.status}: {error_text}")
                            return None
            except Exception as e:
                logger.error(f"Request failed (Attempt {attempt+1}): {e}")
                await asyncio.sleep(2 ** attempt)
        
        return None
    
    async def _prepare_gemini_request(
        self,
        prompt: str,
        image_size: str,
        aspect_ratio: str,
        multimodal_inputs: Optional[List[Dict[str, Any]]]
    ) -> Dict[str, Any]:
        """
        Подготовка запроса с КОРРЕКТНОЙ структурой JSON для Gemini Image Generation.
        """
        parts = [{"text": prompt}]
        
        if multimodal_inputs:
            for input_item in multimodal_inputs[:14]:
                if input_item.get("type") == "image":
                    image_b64 = base64.b64encode(input_item["data"]).decode('utf-8')
                    mime_type = input_item.get("mime_type", "image/png")
                    parts.append({
                        "inline_data": {
                            "mime_type": mime_type,
                            "data": image_b64
                        }
                    })
        
        # Правильная вложенность: generationConfig -> imageConfig
        # imageConfig: { aspectRatio, imageSize } (camelCase for JSON)
        return {
            "contents": [{"parts": parts}],
            "generationConfig": {
                "imageConfig": {
                    "aspectRatio": aspect_ratio,
                    "imageSize": image_size # "2K" or "4K"
                }
            }
        }
    
    async def _prepare_kie_request(
        self,
        prompt: str,
        resolution: str,
        multimodal_inputs: Optional[List[Dict[str, Any]]]
    ) -> Dict[str, Any]:
        """Подготовка запроса для KIE API (формат может отличаться)."""
        return {
            "prompt": prompt,
            "resolution": resolution,
            "multimodal_inputs": multimodal_inputs or [],
            "model": self.model_name
        }
    
    async def _extract_image(self, response: Dict[str, Any]) -> Optional[bytes]:
        """Извлекает изображение из ответа API."""
        try:
            if "candidates" in response:
                candidate = response["candidates"][0]
                if "content" in candidate:
                    parts = candidate["content"].get("parts", [])
                    for part in parts:
                        if "inlineData" in part: # В ответе может быть inlineData (без underscore)
                            image_b64 = part["inlineData"]["data"]
                            return base64.b64decode(image_b64)
            
            logger.warning(f"Could not find image in response: {json.dumps(response)[:200]}...")
            return None
        except Exception as e:
            logger.error(f"Failed to extract image: {e}")
            return None
    
    async def edit_image(
        self,
        original_image: bytes,
        instructions: str,
        reference_images: Optional[List[Dict[str, Any]]] = None,
        resolution: str = RESOLUTION_2K,
        aspect_ratio: str = "1:1",
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Редактирование изображения через NBP с сохранением разрешения и формата.
        """
        if not self.api_key:
            logger.error("NBP API key not configured")
            return None
        
        try:
            multimodal_inputs = []
            
            # Добавляем оригинальное изображение первым
            multimodal_inputs.append({
                "type": "image",
                "data": original_image,
                "mime_type": "image/png"
            })
            
            if reference_images:
                for ref_img in reference_images[:13]:
                    multimodal_inputs.append(ref_img)
            
            edit_prompt = f"Change the image: {instructions}"

            # Используем generate_with_fallback, который уже использует _prepare_gemini_request
            # с правильной конфигурацией aspect_ratio и imageSize
            return await self.generate_with_fallback(
                prompt=edit_prompt,
                resolution=resolution,
                aspect_ratio=aspect_ratio,
                multimodal_inputs=multimodal_inputs,
                user_id=user_id
            )
            
        except Exception as e:
            logger.error(f"Image editing failed: {e}")
            return None

# Глобальный экземпляр клиента
nbp_client = NBPClient()
