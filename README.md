# Game List Bot

Symfony-проект с Telegram-ботом на `Nutgram` и хранением игр в PostgreSQL через `Doctrine`.

## Что умеет бот

- `/add_game {ссылка} [описание]`: добавляет игру в БД; для Steam-ссылки без описания использует краткое описание из Steam
- `/add_games`: добавляет несколько игр сразу, если после команды передать несколько блоков `ссылка + описание` (описание необязательно для Steam-ссылок), разделённых пустой строкой
- `/get_games`: показывает игры со статусом `новая`
- `/get_done_games`: показывает игры со статусом `пройдена`
- `/get_dropped_games`: показывает игры со статусом `брошена`
- `/find_game {название}`: ищет одну игру по названию, без учёта регистра и с допуском к небольшим опечаткам
- `/done_game {game-id}`: меняет статус на `пройдена`
- `/drop_game {game-id}`: меняет статус на `брошена`
- `/delete_game {game-id}`: полностью удаляет игру из БД
- если ссылка ведёт на `store.steampowered.com/app/...`, бот при добавлении попытается сохранить название игры, `Steam App ID`, цену, цену в рублях, недавние отзывы и все отзывы

Описание игры сохраняется вместе с Telegram `entities`, поэтому переносы строк и встроенное форматирование Telegram по возможности восстанавливаются при выдаче списка.

Пример пакетного добавления:

```text
/add_games https://store.steampowered.com/app/2956680/LORT/
LORT — action roguelike / third-person shooter / looter-shooter | РД: да | стиль: стилизованная гротескная 3D | год: 2026 | сеттинг: проклятый фэнтези-мир.

https://store.steampowered.com/app/312670/?snr=1_5_9__205
Strange Brigade — action-adventure / third-person shooter / co-op | РД: нет | стиль: pulp-приключение, полу-реалистичная 3D | год: 2018 | сеттинг: Египет 1930-х, оккультизм и мифологические руины.
```

## Настройка

Заполни в `.env.local`:

```dotenv
TELEGRAM_TOKEN=токен_бота
TELEGRAM_BOT_NAME=имя_бота_без_собаки
TELEGRAM_PROXY_URL=http://user:password@host:port
TELEGRAM_CLIENT_TIMEOUT=10
TELEGRAM_POLLING_TIMEOUT=5
TELEGRAM_ENABLE_HTTP2=0
STEAM_STORE_COUNTRY=us
STEAM_STORE_LANGUAGE=russian
DATABASE_URL="postgresql://master:secure123@127.0.0.1:15432/story-teller?serverVersion=16&charset=utf8"
```

Если запускаешь внутри контейнера `php`:

```dotenv
DATABASE_URL="postgresql://master:secure123@postgres:5432/story-teller?serverVersion=16&charset=utf8"
```

`TELEGRAM_BOT_NAME` нужен для корректной обработки команд вида `/command@your_bot` в группах.
`TELEGRAM_POLLING_TIMEOUT` задаёт, сколько секунд `getUpdates` ждёт ответ от Telegram в long polling режиме. Для работы через прокси лучше держать его небольшим, например `5`.
`TELEGRAM_CLIENT_TIMEOUT` должен быть больше `TELEGRAM_POLLING_TIMEOUT`.
`TELEGRAM_ENABLE_HTTP2=0` отключает HTTP/2. Для HTTPS через прокси это обычно стабильнее.
`STEAM_STORE_COUNTRY` определяет регион магазина для цены. `STEAM_STORE_LANGUAGE` влияет на язык review summary от Steam.

## Обновление Steam-данных у уже сохранённых игр

Обновить одну игру по ID:

```bash
php bin/console app:refresh-steam-metadata --id=12 --env=prod
```

Обновить все игры со Steam-ссылками:

```bash
php bin/console app:refresh-steam-metadata --all-steam --env=prod
```

## Запуск

Поднять контейнеры:

```bash
docker compose up -d
```

Применить миграции:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate -n
```

Зарегистрировать команды в Telegram:

```bash
docker compose exec php php bin/console nutgram:register-commands
```

Запустить бота в long polling:

```bash
docker compose exec php php bin/console nutgram:run
```

В меню Telegram регистрируются только команды без параметров. Команды `/add_game`, `/add_games`, `/find_game`, `/done_game`, `/drop_game`, `/delete_game` нужно вводить руками вместе с параметрами. Это сделано специально, чтобы Telegram не отправлял их случайно пустыми при выборе из меню.

## Сервер

Для домашнего сервера без домена и HTTPS используй `long polling`, а не webhook. В проект уже добавлен отдельный docker-сервис `bot`, который крутит `nutgram:run`.

Подготовка:

1. Клонируй репозиторий на сервер.
2. Создай `.env.local` на основе [.env.local.example](/home/reik/projects/php/game-list-bot/.env.local.example).
3. Укажи production-значения:
   - `APP_ENV=prod`
   - `APP_DEBUG=0`
   - `TELEGRAM_TOKEN`
   - `TELEGRAM_BOT_NAME`
   - `TELEGRAM_PROXY_URL`, если нужен прокси
4. Собери и подними контейнеры:

```bash
docker compose --profile bot up -d --build
```

5. Один раз выполни миграции и регистрацию команд:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate -n --env=prod
docker compose exec php php bin/console nutgram:register-commands --env=prod
```

После этого бот будет работать через отдельный контейнер `bot` и переживать перезапуск Docker благодаря `restart: unless-stopped`.

Полезные команды на сервере:

```bash
docker compose logs -f bot
docker compose ps
docker compose restart bot
```

## Деплой без Docker

Если сервер слабый, можно разворачивать проект как обычное PHP CLI-приложение без контейнеров. Для этого в репозиторий добавлен скрипт [scripts/deploy.sh](/home/reik/projects/php/game-list-bot/scripts/deploy.sh).

Что должно быть на сервере заранее:

- `php` 8.2+
- `composer`
- `postgresql`
- расширения PHP `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `curl`, `intl`, `zip`
- настроенный `.env.local`

Подготовка:

1. Клонируй репозиторий.
2. Создай `.env.local` на основе [.env.local.example](/home/reik/projects/php/game-list-bot/.env.local.example).
3. Пропиши production-значения:
   - `APP_ENV=prod`
   - `APP_DEBUG=0`
   - `DATABASE_URL`, который указывает на локальный PostgreSQL
   - `TELEGRAM_TOKEN`
   - `TELEGRAM_BOT_NAME`
   - `TELEGRAM_PROXY_URL`, если Telegram с сервера недоступен напрямую
   - `TELEGRAM_POLLING_TIMEOUT=5`
   - `TELEGRAM_CLIENT_TIMEOUT=10`
   - `TELEGRAM_ENABLE_HTTP2=0`
4. Сделай скрипт исполняемым:

```bash
chmod +x scripts/deploy.sh
```

Обычный деплой после `git pull`:

```bash
./scripts/deploy.sh
```

### Деплой из GitHub Actions

Workflow [.github/workflows/deploy.yml](/home/reik/projects/php/game-list-bot/.github/workflows/deploy.yml) запускается только вручную через кнопку **Run workflow**. Он выполняет тесты, а затем подключается к серверу, проверяет чистоту рабочей копии, переключает её на протестированный commit, применяет миграции и перезапускает бота.

Перед первым запуском добавь в GitHub repository secrets:

- `DEPLOY_SSH_PRIVATE_KEY` — закрытый ключ пользователя `reikserver` для подключения к серверу;
- `DEPLOY_KNOWN_HOSTS` — строка из `known_hosts` для `91.214.240.166:2222`.

Для отдельного подтверждения перед выкладкой создай environment `production` в настройках репозитория и включи **Required reviewers**. Workflow уже использует это environment: GitHub покажет кнопку подтверждения после успешных тестов.

Что делает скрипт:

- запускает `composer install --no-dev`
- применяет миграции `Doctrine`
- очищает и прогревает кэш Symfony
- регистрирует команды Telegram через `nutgram:register-commands`

Если бот запущен как `systemd`-сервис, можно сразу перезапустить его тем же скриптом:

```bash
./scripts/deploy.sh --service game-list-bot
```

Если перезапуск нужен позже:

```bash
sudo systemctl restart game-list-bot
sudo systemctl status game-list-bot
```

Шаблон `systemd`-юнита лежит в [deploy/game-list-bot.service.example](/home/reik/projects/php/game-list-bot/deploy/game-list-bot.service.example). Скопируй его в `/etc/systemd/system/game-list-bot.service`, подставь своего пользователя и путь к проекту, затем выполни:

```bash
sudo systemctl daemon-reload
sudo systemctl enable game-list-bot
sudo systemctl start game-list-bot
```

## Debug

В проекте уже включён `Xdebug` для PHP-контейнера.

После изменения `docker-compose.yml` контейнер `php` нужно пересобрать:

```bash
docker compose up -d --build php
```

### PhpStorm

1. Открой `Settings -> PHP`.
2. Добавь интерпретатор `From Docker Compose`.
3. Выбери сервис `php`.
4. Открой `Settings -> PHP -> Servers`.
5. Добавь сервер:
   - `Name`: `game-list-bot`
   - `Host`: `localhost`
   - `Port`: `8080`
   - `Debugger`: `Xdebug`
   - включи `Use path mappings`
   - сопоставь путь проекта с `/var/www/app`
6. Нажми `Start Listening for PHP Debug Connections`.

### Как дебажить команды

CLI-команды будут автоматически стартовать Xdebug, потому что в контейнере включён `xdebug.start_with_request=yes`.

Для отладки команд запускай их так:

```bash
docker compose exec php php bin/console nutgram:register-commands
docker compose exec php php bin/console nutgram:run
```

Ставь breakpoint, например, в:

- `src/Telegram/Service/GameBotHandler.php`
- `src/Telegram/Service/GameMessageParser.php`
- `config/telegram.php`

Если IDE не ловит соединение, проверь лог Xdebug:

```bash
docker compose exec php cat /tmp/xdebug.log
```

## PostgreSQL

Подключение с хоста:

- `Host`: `127.0.0.1`
- `Port`: `15432`
- `Database`: `story-teller`
- `User`: `master`
- `Password`: `secure123`

URL для IDE / клиента:

```text
postgresql://master:secure123@127.0.0.1:15432/story-teller
```

Подключение из контейнера `php`:

```text
postgresql://master:secure123@postgres:5432/story-teller
```
