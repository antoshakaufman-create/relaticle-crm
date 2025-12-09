# Руководство по внесению вклада в Relaticle

Спасибо за интерес к проекту Relaticle! Мы рады вашему вкладу.

## Настройка окружения разработки

### Требования

- PHP 8.4+
- Composer 2
- Node.js 20+
- PostgreSQL 15+ (или SQLite для разработки)
- Redis (опционально, для очередей)

### Установка

1. Клонируйте репозиторий:
```bash
git clone https://github.com/Relaticle/relaticle.git
cd relaticle
```

2. Установите зависимости:
```bash
composer install
npm install
```

3. Настройте окружение:
```bash
cp .env.example .env
php artisan key:generate
```

4. Настройте базу данных в `.env`:
```env
DB_CONNECTION=sqlite
# или
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=relaticle
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. Запустите миграции:
```bash
php artisan migrate
```

6. Запустите приложение:
```bash
composer dev
```

Это запустит:
- Laravel сервер разработки
- Очередь задач
- Логи в реальном времени
- Vite для сборки фронтенда

## Стандарты кодирования

### PHP

Проект использует:
- **Laravel Pint** для форматирования кода
- **PHPStan** для статического анализа
- **Rector** для рефакторинга

Перед коммитом выполните:

```bash
composer lint
```

Это выполнит:
- `pint` - форматирование кода
- `rector` - рефакторинг
- `phpstan` - статический анализ

### JavaScript/CSS

Проект использует:
- **Tailwind CSS 4** для стилей
- **Vite** для сборки

Форматирование выполняется автоматически при коммите.

## Тестирование

### Запуск тестов

```bash
# Все тесты
composer test

# Только unit тесты
php artisan test --testsuite=Unit

# Только feature тесты
php artisan test --testsuite=Feature

# Конкретный тест
php artisan test tests/Feature/YourTest.php
```

### Написание тестов

- Используйте **Pest PHP** для написания тестов
- Тесты должны быть в директории `tests/`
- Feature тесты в `tests/Feature/`
- Unit тесты в `tests/Unit/`

Пример теста:

```php
<?php

use App\Models\User;

it('can create a user', function () {
    $user = User::factory()->create();
    
    expect($user)->toBeInstanceOf(User::class);
});
```

## Процесс создания Pull Request

1. **Создайте ветку** от `main`:
```bash
git checkout -b feature/your-feature-name
```

2. **Внесите изменения** и закоммитьте:
```bash
git add .
git commit -m "Add: описание ваших изменений"
```

3. **Убедитесь, что все проверки проходят**:
```bash
composer lint
composer test
```

4. **Запушьте изменения**:
```bash
git push origin feature/your-feature-name
```

5. **Создайте Pull Request** на GitHub:
   - Опишите изменения
   - Укажите связанные issues (если есть)
   - Добавьте скриншоты (если применимо)

### Формат коммитов

Используйте префиксы для коммитов:
- `Add:` - новая функциональность
- `Fix:` - исправление бага
- `Update:` - обновление существующей функциональности
- `Refactor:` - рефакторинг кода
- `Docs:` - изменения в документации
- `Test:` - добавление или изменение тестов

Примеры:
```
Add: возможность экспорта компаний в CSV
Fix: ошибка при сохранении пользователя
Update: улучшена производительность запросов
```

## Структура проекта

```
relaticle/
├── app/                    # Основной код приложения
│   ├── Filament/          # Filament ресурсы и страницы
│   ├── Models/            # Eloquent модели
│   ├── Services/          # Бизнес-логика
│   └── ...
├── app-modules/           # Модули приложения
│   ├── SystemAdmin/       # Административная панель
│   ├── Documentation/     # Модуль документации
│   └── OnboardSeed/       # Сидеры для онбординга
├── database/              # Миграции, фабрики, сидеры
├── resources/             # Views, CSS, JS
├── routes/               # Маршруты
└── tests/                # Тесты
```

## Работа с модулями

Проект использует модульную архитектуру. Модули находятся в `app-modules/`.

Каждый модуль должен иметь:
- `src/` - исходный код
- `README.md` - описание модуля
- Собственный ServiceProvider

## Документация

- Обновляйте документацию при изменении API
- Добавляйте примеры использования
- Обновляйте README при добавлении новых функций

## Вопросы?

Если у вас есть вопросы:
- Создайте [Discussion](https://github.com/Relaticle/relaticle/discussions)
- Откройте [Issue](https://github.com/Relaticle/relaticle/issues)
- Присоединитесь к нашему сообществу

## Лицензия

Внося вклад в Relaticle, вы соглашаетесь с тем, что ваш вклад будет лицензирован под [AGPL-3.0](LICENSE).

Спасибо за ваш вклад!

