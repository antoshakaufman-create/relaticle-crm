"""
Утилиты для временного хранения медиафайлов.
Поддерживает локальное хранилище, FTP, и облачные хранилища (S3/GCS/Yandex).
"""
import os
import tempfile
from pathlib import Path
from typing import Optional, BinaryIO
from datetime import datetime, timedelta
import shutil

from src.config import (
    STORAGE_TYPE,
    STORAGE_LOCAL_PATH,
    STORAGE_FTP_HOST,
    STORAGE_FTP_USER,
    STORAGE_FTP_PASSWORD,
    STORAGE_FTP_PATH,
    STORAGE_BUCKET_NAME,
    STORAGE_ACCESS_KEY,
    STORAGE_SECRET_KEY,
    STORAGE_REGION,
    MEDIA_CLEANUP_HOURS
)
from src.utils.logger import logger


class StorageManager:
    """
    Менеджер для временного хранения медиафайлов.
    Автоматически удаляет старые файлы для соблюдения конфиденциальности.
    """
    
    def __init__(self):
        self.storage_type = STORAGE_TYPE
        self._setup_storage()
    
    def _setup_storage(self):
        """Настройка хранилища в зависимости от типа."""
        if self.storage_type == "local":
            # Создаем локальную директорию если не существует
            Path(STORAGE_LOCAL_PATH).mkdir(parents=True, exist_ok=True)
            logger.info(f"Local storage initialized at: {STORAGE_LOCAL_PATH}")
        elif self.storage_type == "ftp":
            if not all([STORAGE_FTP_HOST, STORAGE_FTP_USER, STORAGE_FTP_PASSWORD]):
                raise ValueError("FTP storage requires FTP_HOST, FTP_USER, FTP_PASSWORD")
            logger.info(f"FTP storage configured: {STORAGE_FTP_HOST}")
        elif self.storage_type in ["s3", "gcs", "yandex"]:
            if not all([STORAGE_BUCKET_NAME, STORAGE_ACCESS_KEY, STORAGE_SECRET_KEY]):
                raise ValueError(
                    f"{self.storage_type.upper()} storage requires "
                    "BUCKET_NAME, ACCESS_KEY, SECRET_KEY"
                )
            logger.info(f"{self.storage_type.upper()} storage configured: {STORAGE_BUCKET_NAME}")
        else:
            raise ValueError(f"Unknown storage type: {self.storage_type}")
    
    async def save_file(
        self,
        file_data: bytes,
        filename: str,
        user_id: Optional[int] = None
    ) -> str:
        """
        Сохраняет файл во временное хранилище.
        
        Args:
            file_data: Байты файла
            filename: Имя файла
            user_id: ID пользователя (для организации файлов)
            
        Returns:
            str: Путь к сохраненному файлу (URL для облачного хранилища)
        """
        if self.storage_type == "local":
            return await self._save_local(file_data, filename, user_id)
        elif self.storage_type == "ftp":
            return await self._save_ftp(file_data, filename, user_id)
        elif self.storage_type in ["s3", "gcs", "yandex"]:
            return await self._save_cloud(file_data, filename, user_id)
        else:
            raise ValueError(f"Unsupported storage type: {self.storage_type}")
    
    async def _save_local(
        self,
        file_data: bytes,
        filename: str,
        user_id: Optional[int]
    ) -> str:
        """Сохранение в локальное хранилище."""
        # Создаем поддиректорию для пользователя если указан
        if user_id:
            user_dir = Path(STORAGE_LOCAL_PATH) / str(user_id)
            user_dir.mkdir(parents=True, exist_ok=True)
            file_path = user_dir / filename
        else:
            file_path = Path(STORAGE_LOCAL_PATH) / filename
        
        # Сохраняем файл
        with open(file_path, "wb") as f:
            f.write(file_data)
        
        logger.info(f"File saved locally: {file_path}")
        return str(file_path)
    
    async def _save_ftp(
        self,
        file_data: bytes,
        filename: str,
        user_id: Optional[int]
    ) -> str:
        """Сохранение через FTP."""
        try:
            from ftplib import FTP
            
            ftp = FTP(STORAGE_FTP_HOST)
            ftp.login(STORAGE_FTP_USER, STORAGE_FTP_PASSWORD)
            
            # Создаем путь
            remote_path = STORAGE_FTP_PATH
            if user_id:
                remote_path = f"{remote_path}/{user_id}"
            
            try:
                ftp.cwd(remote_path)
            except:
                # Создаем директорию если не существует
                ftp.mkd(remote_path)
                ftp.cwd(remote_path)
            
            # Сохраняем файл
            ftp.storbinary(f"STOR {filename}", file_data)
            ftp.quit()
            
            logger.info(f"File saved via FTP: {remote_path}/{filename}")
            return f"ftp://{STORAGE_FTP_HOST}{remote_path}/{filename}"
        except Exception as e:
            logger.error(f"FTP save failed: {e}")
            raise
    
    async def _save_cloud(
        self,
        file_data: bytes,
        filename: str,
        user_id: Optional[int]
    ) -> str:
        """Сохранение в облачное хранилище."""
        # TODO: Реализовать интеграцию с boto3 (S3), google-cloud-storage (GCS)
        # или yandex-cloud SDK для Yandex Object Storage
        logger.warning(f"Cloud storage ({self.storage_type}) not yet implemented")
        # Временно используем локальное хранилище
        return await self._save_local(file_data, filename, user_id)
    
    async def delete_file(self, file_path: str) -> bool:
        """
        Удаляет файл из хранилища.
        
        Args:
            file_path: Путь к файлу
            
        Returns:
            bool: True если файл удален успешно
        """
        try:
            if self.storage_type == "local":
                if os.path.exists(file_path):
                    os.remove(file_path)
                    logger.info(f"File deleted: {file_path}")
                    return True
            elif self.storage_type == "ftp":
                # TODO: Реализовать удаление через FTP
                logger.warning("FTP delete not yet implemented")
            elif self.storage_type in ["s3", "gcs", "yandex"]:
                # TODO: Реализовать удаление из облачного хранилища
                logger.warning(f"Cloud delete ({self.storage_type}) not yet implemented")
            
            return False
        except Exception as e:
            logger.error(f"Failed to delete file {file_path}: {e}")
            return False
    
    async def cleanup_old_files(self):
        """
        Удаляет старые файлы (старше MEDIA_CLEANUP_HOURS часов).
        Должен вызываться периодически для соблюдения конфиденциальности.
        """
        if self.storage_type != "local":
            logger.warning("Cleanup only implemented for local storage")
            return
        
        cleanup_time = datetime.now() - timedelta(hours=MEDIA_CLEANUP_HOURS)
        deleted_count = 0
        
        storage_path = Path(STORAGE_LOCAL_PATH)
        if not storage_path.exists():
            return
        
        for file_path in storage_path.rglob("*"):
            if file_path.is_file():
                file_time = datetime.fromtimestamp(file_path.stat().st_mtime)
                if file_time < cleanup_time:
                    try:
                        file_path.unlink()
                        deleted_count += 1
                    except Exception as e:
                        logger.error(f"Failed to delete old file {file_path}: {e}")
        
        if deleted_count > 0:
            logger.info(f"Cleaned up {deleted_count} old files")


# Глобальный экземпляр менеджера хранилища
storage_manager = StorageManager()

