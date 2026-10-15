# Mini Notes

Мини-сервис заметок по тестовому заданию: создание, редактирование, удаление, просмотр одной заметки и списка. Одна страница на React с формой создания и редактирования и подтверждением удаления в модальном окне shadcn/ui.

**Стек:** PHP 8.3, Symfony 7.4 LTS, Doctrine DBAL, MySQL 8.4, React 19, TypeScript, Vite, Tailwind CSS 4, shadcn/ui (Radix), Nginx, Docker Compose.

## Запуск

Нужны Docker Engine и Docker Compose v2+. Локальные PHP, Composer и Node.js для запуска не нужны.

```bash
git clone git@github.com:z452k5/mini_notes.git
cd mini_notes
docker compose up -d --build
```

Откройте http://localhost. Первая сборка скачивает зависимости и образы. После `docker compose ps` контейнеры `api` и `db` должны быть healthy.

Для домена http://mini_notes.local добавьте в `/etc/hosts`:

```text
127.0.0.1 mini_notes.local
```

Также поддерживается адрес `127.0.1.1`, используемый некоторыми локальными настройками hosts. Веб-сервис слушает только эти два локальных адреса. MySQL и PHP-FPM доступны только внутри Docker-сети.

`.env` необязателен: Compose содержит рабочие локальные значения по умолчанию. Для изменения настроек:

```bash
cp .env.example .env
```

| Переменная | Значение по умолчанию | Назначение |
| --- | --- | --- |
| `APP_PORT` | `80` | Локальный HTTP-порт; если занят, задайте `8080` |
| `APP_SECRET` | локальный секрет | Секрет Symfony |
| `MYSQL_PASSWORD` | `notes_local_password` | Пароль пользователя приложения |
| `MYSQL_ROOT_PASSWORD` | `root_local_password` | Пароль администратора MySQL |
| `DOCKER_SUBNET` | `172.29.240.0/24` | Подсеть Compose; явное значение помогает при VPN-маршрутах |

Если порт изменён на 8080, используйте http://mini_notes.local:8080. Значения паролей в `DATABASE_URL` должны быть URL-safe (например, буквы, цифры и дефис). При существующем томе MySQL изменение переменной пароля не меняет пароль пользователя в самой БД.

При старте API идемпотентно создаёт таблицу из `backend/migrations/001_create_notes.sql`, прогревает кеш и запускает PHP-FPM. Данные сохраняются в томе `mini-notes_mysql_data`.

```bash
docker compose logs -f       # логи
docker compose down          # остановить, сохранить данные
docker compose up -d         # запустить снова
```

`docker compose down -v` удаляет данные. Авторизация и разделение пользователей не входят в ТЗ: все заметки принадлежат общему локальному пространству.

## API

Swagger UI: http://mini_notes.local/docs/ (или http://localhost/docs/). OpenAPI: [docs/openapi.yaml](docs/openapi.yaml), доступен также по `/openapi.yaml`. Для загрузки Swagger UI нужен доступ к CDN; сама спецификация доступна локально.

| Метод | URL | Результат |
| --- | --- | --- |
| `POST` | `/api/notes` | Создать, `201` и заголовок `Location` |
| `PUT` | `/api/notes/{id}` | Обновить заголовок и текст, `200` |
| `DELETE` | `/api/notes/{id}` | Удалить, `204` без тела |
| `GET` | `/api/notes/{id}` | Получить одну заметку, `200` |
| `GET` | `/api/notes?limit=100&offset=0` | Список, `200` |
| `GET` | `/api/health` | Проверка API и таблицы MySQL |

Создание и обновление требуют `Content-Type: application/json` и оба поля:

```json
{"title":"Первая заметка","content":"Текст заметки"}
```

`title` — строка от 1 до 200 символов, не только пробелы. `content` — строка до 10 000 символов, может быть пустой. Неизвестные поля запрещены. Крайние пробелы удаляются. Длина считается в Unicode-символах. `id` проверяется как UUID v4. Список валидирует `limit` (1–100), `offset` (0–1 000 000) и отклоняет неизвестные параметры.

Ответ заметки: `id`, `title`, `content`, `createdAt`, `updatedAt` (UTC). Список содержит `data`, `total`, `limit`, `offset`, сортируется по дате изменения от новых к старым. Кнопка «Загрузить ещё» позволяет получить остальные записи. Одновременное изменение одной заметки использует последнюю сохранённую версию.

Ошибки: `400` — JSON/UUID/пагинация, `404` — заметка не найдена, `405` — метод не поддерживается, `415` — неверный Content-Type, `422` — поля не прошли валидацию. Непредвиденные ошибки дают `500` с общим сообщением, подробности попадают в логи контейнера.

```json
{"error":{"status":404,"message":"Заметка не найдена."}}
```

Пример:

```bash
curl http://mini_notes.local/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"title":"Проверить сервис","content":"Создать, изменить, удалить"}'
```

## Архитектура

Бэкенд организован как небольшой модуль `Notes`, без избыточных слоёв для простого CRUD:

```text
backend/src/
  Notes/
    Domain/          Note, интерфейс NoteRepository; инварианты без Symfony и MySQL
    Application/     NoteService; сценарии CRUD, зависит от интерфейса репозитория
    Infrastructure/  MysqlNoteRepository, команда миграции
    Http/            REST-контроллеры, проверка входных данных Symfony Validator
  Shared/
    Http/            единый формат ошибок
    Infrastructure/  фабрика DBAL-соединения
```

Контроллеры отвечают за HTTP, сервис — за сценарии, репозиторий — за хранение. Домен сохраняет собственные ограничения независимо от входного интерфейса. Зависимость `NoteRepository` внедряется через контейнер Symfony. Запросы параметризованы; UI выводит текст средствами React без HTML-инъекций.

Фронтенд содержит одну страницу, общий API-клиент, типы и компоненты shadcn/ui. Анимации модальных окон учитывают `prefers-reduced-motion`.

## Проверки

PHPUnit: доменные ограничения, Unicode, полный CRUD в настоящей MySQL, ответы ошибок и валидация всех пяти методов. Функциональные тесты создают свои записи и удаляют их после проверки.

```bash
docker compose exec -T api vendor/bin/phpunit
docker compose exec -T api composer validate --strict
```

Браузерные тесты: CRUD, сохранение после перезагрузки, подтверждение удаления, мобильный экран и валидация формы. Нужен Node.js 20.19+ или 22.12+.

```bash
cd frontend
npm ci
npx playwright install --with-deps chromium
npm run test:e2e
```

По умолчанию тесты используют `http://127.0.0.1`. Для другого порта: `BASE_URL=http://127.0.0.1:8080 npm run test:e2e`. Для установленного Chrome: `CHROME_PATH=/usr/bin/google-chrome npm run test:e2e`.

Локальная разработка UI: `cd frontend && npm ci && npm run dev`. Vite проксирует `/api` в запущенный Docker-сервис на локальном порту 80.

GitHub Actions собирает Docker-образы, запускает MySQL и приложение, выполняет PHPUnit и браузерные тесты.
