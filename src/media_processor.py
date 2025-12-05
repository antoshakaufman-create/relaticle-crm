"""
Media Processor - Обработка медиафайлов (фото, документы, голосовые сообщения).
Транскрипция голосовых сообщений через Gemini 2.5 Flash-Lite.
"""
import aiohttp
from typing import Optional, Dict, Any, List
from io import BytesIO

import google.generativeai as genai

from src.config import (
    TELEGRAM_BOT_TOKEN,
    GEMINI_API_KEY,
    GEMINI_MODEL,
    MEDIA_DOWNLOAD_TIMEOUT,
    AI_REQUEST_TIMEOUT
)
from src.utils.logger import logger
from src.utils.storage import storage_manager


class MediaProcessor:
    """
    Процессор медиафайлов для бота.
    Обрабатывает фото, документы и транскрибирует голосовые сообщения.
    """
    
    def __init__(self):
        genai.configure(api_key=GEMINI_API_KEY)
        self.model = genai.GenerativeModel(GEMINI_MODEL)
        self.bot_token = TELEGRAM_BOT_TOKEN
    
    async def download_file(
        self,
        file_id: str,
        user_id: Optional[int] = None
    ) -> Optional[bytes]:
        """
        Скачивает файл из Telegram.
        
        Args:
            file_id: Telegram file_id
            user_id: ID пользователя (для временного хранения)
            
        Returns:
            bytes: Содержимое файла или None при ошибке
        """
        try:
            # Получаем информацию о файле
            file_info_url = f"https://api.telegram.org/bot{self.bot_token}/getFile"
            async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=MEDIA_DOWNLOAD_TIMEOUT)) as session:
                async with session.get(file_info_url, params={"file_id": file_id}) as response:
                    if response.status != 200:
                        logger.error(f"Failed to get file info: {response.status}")
                        return None
                    
                    file_info = await response.json()
                    if not file_info.get("ok"):
                        logger.error(f"Telegram API error: {file_info}")
                        return None
                    
                    file_path = file_info["result"]["file_path"]
                    
                    # Скачиваем файл
                    file_url = f"https://api.telegram.org/file/bot{self.bot_token}/{file_path}"
                    async with session.get(file_url) as file_response:
                        if file_response.status == 200:
                            file_data = await file_response.read()
                            logger.info(f"File downloaded: {len(file_data)} bytes")
                            
                            # Сохраняем во временное хранилище
                            filename = file_path.split("/")[-1]
                            stored_path = await storage_manager.save_file(
                                file_data,
                                filename,
                                user_id
                            )
                            
                            return file_data
                        else:
                            logger.error(f"Failed to download file: {file_response.status}")
                            return None
        except Exception as e:
            logger.error(f"File download failed: {e}")
            return None
    
    async def transcribe_voice(
        self,
        voice_file: bytes,
        user_id: Optional[int] = None
    ) -> Optional[str]:
        """
        Транскрибирует голосовое сообщение через Gemini 2.5 Flash-Lite.
        ⚠️ С предупреждением о конфиденциальности (Free Tier).
        
        Args:
            voice_file: Байты аудиофайла
            user_id: ID пользователя
            
        Returns:
            str: Транскрипция текста или None при ошибке
        """
        try:
            # ⚠️ ПРЕДУПРЕЖДЕНИЕ О КОНФИДЕНЦИАЛЬНОСТИ
            logger.info("Transcribing voice via Gemini (Free Tier - confidentiality warning)")
            
            # Подготовка аудио для Gemini
            # Gemini может принимать аудио в различных форматах
            # Конвертируем в base64 если необходимо
            import base64
            audio_b64 = base64.b64encode(voice_file).decode('utf-8')
            
            # Определяем MIME тип (Telegram обычно отправляет OGG)
            mime_type = "audio/ogg"
            
            # Вызов Gemini для транскрипции
            prompt = "Transcribe this audio message to text. Return only the transcribed text without any additional comments."
            
            # Создаем контент с аудио
            import asyncio
            loop = asyncio.get_event_loop()
            
            # Используем Gemini для обработки аудио
            # Примечание: Точный формат API может отличаться
            response = await loop.run_in_executor(
                None,
                lambda: self.model.generate_content([
                    prompt,
                    {
                        "mime_type": mime_type,
                        "data": audio_b64
                    }
                ])
            )
            
            transcription = response.text.strip()
            logger.info(f"Voice transcribed: {len(transcription)} characters")
            return transcription
        except Exception as e:
            logger.error(f"Voice transcription failed: {e}")
            # Fallback: можно попробовать использовать Telegram Bot API транскрипцию
            # или другой сервис
            return None
    
    async def process_photo(
        self,
        photo_file_id: str,
        user_id: Optional[int] = None
    ) -> Optional[Dict[str, Any]]:
        """
        Обрабатывает фото для мультимодального ввода.
        
        Args:
            photo_file_id: Telegram file_id фото
            user_id: ID пользователя
            
        Returns:
            Dict с данными фото для мультимодального ввода
        """
        try:
            photo_data = await self.download_file(photo_file_id, user_id)
            if not photo_data:
                return None
            
            return {
                "type": "image",
                "data": photo_data,
                "mime_type": "image/jpeg"  # Telegram обычно отправляет JPEG
            }
        except Exception as e:
            logger.error(f"Photo processing failed: {e}")
            return None
    
    async def process_document(
        self,
        document_file_id: str,
        user_id: Optional[int] = None
    ) -> Optional[Dict[str, Any]]:
        """
        Обрабатывает документ (может быть изображением).
        
        Args:
            document_file_id: Telegram file_id документа
            user_id: ID пользователя
            
        Returns:
            Dict с данными документа или None если не изображение
        """
        try:
            document_data = await self.download_file(document_file_id, user_id)
            if not document_data:
                return None
            
            # Проверяем, является ли документ изображением
            # Простая проверка по первым байтам (magic numbers)
            if document_data[:3] == b'\xff\xd8\xff':  # JPEG
                mime_type = "image/jpeg"
            elif document_data[:8] == b'\x89PNG\r\n\x1a\n':  # PNG
                mime_type = "image/png"
            elif document_data[:6] in [b'GIF87a', b'GIF89a']:  # GIF
                mime_type = "image/gif"
            elif document_data[:2] == b'BM':  # BMP
                mime_type = "image/bmp"
            else:
                logger.warning("Document is not an image, skipping")
                return None
            
            return {
                "type": "image",
                "data": document_data,
                "mime_type": mime_type
            }
        except Exception as e:
            logger.error(f"Document processing failed: {e}")
            return None
    
    async def prepare_multimodal_inputs(
        self,
        photos: Optional[List[str]] = None,
        documents: Optional[List[str]] = None,
        user_id: Optional[int] = None
    ) -> List[Dict[str, Any]]:
        """
        Подготавливает список мультимодальных входов для NBP (до 14 элементов).
        
        Args:
            photos: Список file_id фото
            documents: Список file_id документов
            user_id: ID пользователя
            
        Returns:
            List мультимодальных входов
        """
        multimodal_inputs = []
        
        # Обрабатываем фото
        if photos:
            for photo_id in photos[:14]:  # Лимит 14 элементов
                photo_data = await self.process_photo(photo_id, user_id)
                if photo_data:
                    multimodal_inputs.append(photo_data)
                    if len(multimodal_inputs) >= 14:
                        break
        
        # Обрабатываем документы
        if documents and len(multimodal_inputs) < 14:
            for doc_id in documents[:14 - len(multimodal_inputs)]:
                doc_data = await self.process_document(doc_id, user_id)
                if doc_data:
                    multimodal_inputs.append(doc_data)
                    if len(multimodal_inputs) >= 14:
                        break
        
        logger.info(f"Prepared {len(multimodal_inputs)} multimodal inputs")
        return multimodal_inputs


# Глобальный экземпляр процессора медиа
media_processor = MediaProcessor()

