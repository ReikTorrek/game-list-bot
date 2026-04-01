#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ENV="${APP_ENV:-prod}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SERVICE_NAME=""
NO_RESTART=0

usage() {
    cat <<'EOF'
Использование:
  ./scripts/deploy.sh [--service game-list-bot] [--no-restart]

Опции:
  --service NAME   systemd unit, который нужно перезапустить после деплоя
  --no-restart     не перезапускать сервис даже если он передан
  --help           показать эту справку

Переменные окружения:
  APP_ENV          окружение Symfony, по умолчанию prod
  PHP_BIN          путь к php, по умолчанию php
  COMPOSER_BIN     путь к composer, по умолчанию composer
EOF
}

require_command() {
    local command_name="$1"

    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Не найдена команда: $command_name" >&2
        exit 1
    fi
}

require_env_var() {
    local variable_name="$1"

    if ! grep -Eq "^[[:space:]]*${variable_name}=.+$" "$ROOT_DIR/.env.local"; then
        echo "В .env.local не задана переменная $variable_name" >&2
        exit 1
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --service)
            if [[ $# -lt 2 ]]; then
                echo "Для --service нужно указать имя unit" >&2
                exit 1
            fi
            SERVICE_NAME="$2"
            shift 2
            ;;
        --no-restart)
            NO_RESTART=1
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Неизвестный аргумент: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

require_command "$PHP_BIN"
require_command "$COMPOSER_BIN"

if [[ ! -f "$ROOT_DIR/.env.local" ]]; then
    echo "Файл .env.local не найден. Создай его на основе .env.local.example" >&2
    exit 1
fi

require_env_var "DATABASE_URL"
require_env_var "TELEGRAM_TOKEN"
require_env_var "TELEGRAM_BOT_NAME"

cd "$ROOT_DIR"

echo "==> Установка production-зависимостей"
APP_ENV="$APP_ENV" APP_DEBUG=0 \
    "$COMPOSER_BIN" install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts

echo "==> Миграции Doctrine"
"$PHP_BIN" bin/console doctrine:migrations:migrate -n --env="$APP_ENV"

echo "==> Очистка и прогрев кэша"
"$PHP_BIN" bin/console cache:clear --env="$APP_ENV"
"$PHP_BIN" bin/console cache:warmup --env="$APP_ENV"

echo "==> Регистрация Telegram-команд"
"$PHP_BIN" bin/console nutgram:register-commands --env="$APP_ENV"

if [[ -n "$SERVICE_NAME" && "$NO_RESTART" -eq 0 ]]; then
    require_command systemctl
    echo "==> Перезапуск systemd unit $SERVICE_NAME"
    sudo systemctl restart "$SERVICE_NAME"
    sudo systemctl status "$SERVICE_NAME" --no-pager
elif [[ -n "$SERVICE_NAME" ]]; then
    echo "==> Перезапуск $SERVICE_NAME пропущен флагом --no-restart"
else
    echo "==> Деплой завершён"
    echo "Для перезапуска бота выполни: sudo systemctl restart game-list-bot"
fi
