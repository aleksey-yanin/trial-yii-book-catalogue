.DEFAULT_GOAL := help
DC := docker compose
PHP := $(DC) exec -T php

help: ## Список команд
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk -F':.*?## ' '{printf "  %-12s %s\n", $$1, $$2}'

init: ## Первый запуск: .env, сборка образов, старт
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d

up: ## Поднять окружение
	$(DC) up -d

down: ## Остановить окружение
	$(DC) down

destroy: ## Остановить и удалить данные БД
	$(DC) down -v

build: ## Пересобрать образ php
	$(DC) build

logs: ## Логи всех сервисов
	$(DC) logs -f

sh: ## Шелл внутри контейнера php
	$(DC) exec php bash

composer: ## Composer внутри контейнера: make composer c="require ..."
	$(PHP) composer $(c)

test: ## Прогон тестов Codeception: make test args="Unit"
	$(PHP) vendor/bin/codecept run $(args)

coverage: ## Прогон тестов с покрытием (Xdebug включается только здесь)
	$(DC) exec -T -e XDEBUG_MODE=coverage php vendor/bin/codecept run --coverage --coverage-text $(args)

build-actors: ## Пересобрать актёров Codeception после правки *.suite.yml
	$(PHP) vendor/bin/codecept build

migrate: ## Применить миграции
	$(PHP) php yii migrate --interactive=0

db: ## MySQL-консоль
	$(DC) exec mysql sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

.PHONY: help init up down destroy build logs sh composer test coverage build-actors migrate db
