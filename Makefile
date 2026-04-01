_build: # Инициализация контейнеров
	@make _down-clear \
 	&& docker compose pull \
 	&& docker compose --env-file .env --env-file .env.local build \
 	&& make _up \

_up: # Запустить контейнеры
	@docker compose --env-file .env --env-file .env.local up -d

_down: # Остановить и удалить мусорные контейнеры
	@docker compose --env-file .env --env-file .env.local down --remove-orphans

_down-clear: # Остановить контейнеры, удалить мусорные контейнеры, удалить volumes
	@docker compose --env-file .env --env-file .env.local down --volumes --remove-orphans

_restart: # Перезагрузить контейнеры
	@make _down
	@make _up

_up-back:
	@docker compose --env-file .env --env-file .env.local -f back-compose.yml up -d

_up-full:
	@docker compose --env-file .env --env-file .env.local -f full-compose.yml up -d

_bash: # Войти в контейнер передав имя make bash n=php
	@docker compose exec ${n} bash

_sh: # Войти в контейнер передав имя make sh n=php
	@docker compose exec ${n} sh

_status: # Список запущенных контейнеров
	@docker compose -f full-compose.yml ps -a

_php:
	@make _bash n=php

_api:
	@make _bash n=api

_mysql:
	@make _bash n=mysql

_redis:
	@make _bash n=redis

_front_dev:
	@make _sh n=front_dev

_front_build:
	@make _sh n=front_build

_web_socket:
	@make _bash n=web_socket
