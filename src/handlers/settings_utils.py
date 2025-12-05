"""
Вспомогательные функции для пользовательских настроек генерации.
"""
from telegram.ext import ContextTypes

from src.config import (
    RESOLUTION_1K,
    RESOLUTION_2K,
    RESOLUTION_4K,
    ASPECT_RATIO_1_1,
    ASPECT_RATIO_16_9,
    ASPECT_RATIO_9_16,
    ASPECT_RATIO_4_3,
    ASPECT_RATIO_3_4,
    ASPECT_RATIO_4_5,
    ASPECT_RATIO_5_4,
)


RESOLUTION_LABELS = {
    RESOLUTION_1K: "1K (1024x1024)",
    RESOLUTION_2K: "2K (2048x2048)",
    RESOLUTION_4K: "4K (4096x4096)",
}

ASPECT_LABELS = {
    ASPECT_RATIO_1_1: "1:1",
    ASPECT_RATIO_16_9: "16:9",
    ASPECT_RATIO_9_16: "9:16",
    ASPECT_RATIO_4_3: "4:3",
    ASPECT_RATIO_3_4: "3:4",
    ASPECT_RATIO_4_5: "4:5",
    ASPECT_RATIO_5_4: "5:4",
}

MODEL_LABELS = {
    "nbp": "Gemini 3 Pro",
    "zimage": "Z-Image Turbo",
}


def get_user_resolution(context: ContextTypes.DEFAULT_TYPE) -> str:
    return context.user_data.get("user_resolution", RESOLUTION_2K)


def get_user_aspect_ratio(context: ContextTypes.DEFAULT_TYPE) -> str:
    return context.user_data.get("user_aspect_ratio", ASPECT_RATIO_1_1)


def get_user_model(context: ContextTypes.DEFAULT_TYPE) -> str:
    """Возвращает выбранную модель пользователя (zimage или nbp). По умолчанию zimage."""
    return context.user_data.get("selected_model", "zimage")


def set_user_model(context: ContextTypes.DEFAULT_TYPE, model: str):
    """Сохраняет выбор модели."""
    context.user_data["selected_model"] = model


def format_settings_text(context: ContextTypes.DEFAULT_TYPE) -> str:
    resolution = get_user_resolution(context)
    aspect = get_user_aspect_ratio(context)
    model = get_user_model(context)
    
    return (
        "⚙️ Настройки генерации:\n\n"
        f"• Модель: {MODEL_LABELS.get(model, model)}\n"
        f"• Разрешение: {RESOLUTION_LABELS.get(resolution, resolution)}\n"
        f"• Соотношение сторон: {ASPECT_LABELS.get(aspect, aspect)}\n\n"
        "Выберите новые параметры:"
    )

