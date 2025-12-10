# Переменные окружения для AI модулей

Добавьте следующие переменные в ваш `.env` файл для работы AI модулей поиска и валидации лидов.

## AI Провайдеры

### YandexGPT (Яндекс) - Основной провайдер
```env
# YandexGPT API ключ (получить в Yandex Cloud)
YANDEX_GPT_API_KEY=your-yandex-api-key

# Folder ID в Yandex Cloud
YANDEX_FOLDER_ID=your-folder-id

# Базовый URL (обычно не требуется менять)
YANDEX_GPT_BASE_URL=https://llm.api.cloud.yandex.net/foundationModels/v1

# Модель
YANDEX_GPT_MODEL=yandexgpt/latest

# Таймаут запросов в секундах
YANDEX_GPT_TIMEOUT=60
```

## Выбор провайдера по умолчанию
```env
# Используется только YandexGPT
AI_PROVIDER=yandex
```

## Интеграции с российскими источниками (опционально)

### Контур.Компас
```env
# API ключ Контур.Компас (если есть доступ)
KONTUR_COMPASS_API_KEY=your-kontur-api-key
KONTUR_COMPASS_ENABLED=true
```

### Яндекс.Справочник
```env
# API ключ Яндекс.Справочник
YANDEX_DIRECTORY_API_KEY=your-yandex-directory-api-key
YANDEX_DIRECTORY_ENABLED=true
```

### 2GIS
```env
# API ключ 2GIS
TWO_GIS_API_KEY=your-2gis-api-key
TWO_GIS_ENABLED=true
```

## Пример конфигурации

Для начала работы настройте YandexGPT:

```env
# Конфигурация с YandexGPT
YANDEX_GPT_API_KEY=your-yandex-api-key
YANDEX_FOLDER_ID=your-folder-id
AI_PROVIDER=yandex
```

## Получение API ключей

### GigaChat
1. Зарегистрируйтесь на https://developers.sber.ru/
2. Создайте приложение
3. Получите API ключ

### YandexGPT
1. Зарегистрируйтесь в Yandex Cloud
2. Создайте каталог (folder)
3. Включите YandexGPT API
4. Создайте сервисный аккаунт и получите API ключ
5. Скопируйте Folder ID из каталога

