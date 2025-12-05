# Dockerfile для внутрикорпоративного Telegram AI-бота
# ⚠️ ВАЖНО: Все секреты должны быть переданы через переменные окружения
# В CI/CD эти переменные должны быть настроены как Protected Variables

FROM python:3.12-slim

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Создание рабочей директории
WORKDIR /app

# Копирование requirements и установка зависимостей
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Копирование исходного кода
COPY src/ ./src/

# Создание директории для временных файлов
RUN mkdir -p /tmp/telegram_bot_media && \
    chmod 777 /tmp/telegram_bot_media

# Создание non-root пользователя для безопасности
RUN useradd -m -u 1000 botuser && \
    chown -R botuser:botuser /app /tmp/telegram_bot_media

USER botuser

# Переменные окружения (должны быть установлены при запуске контейнера)
# TELEGRAM_BOT_TOKEN - токен Telegram бота
# ALLOWED_USER_IDS - список разрешенных Telegram ID (через запятую)
# GEMINI_API_KEY - ключ API Gemini
# NBP_API_KEY - ключ API Nano Banana Pro
# SEEDREAM_API_KEY - ключ API Seedream (опционально)
# WEBHOOK_URL - URL для webhook (для production)
# WEBHOOK_SECRET - секретный токен для webhook (опционально)

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD python -c "import requests; requests.get('http://localhost:8000/health')" || exit 1

# Порт для webhook
EXPOSE 8000

# Точка входа
CMD ["python", "-m", "src.main"]

