.DEFAULT_GOAL := help
DC := docker compose
PHP := $(DC) exec -T php

help: ## Список команд
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk -F':.*?## ' '{printf "  %-12s %s\n", $$1, $$2}'

init: ## Первый запуск: .env, сборка образов, старт, миграции с сидером
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d
	$(MAKE) migrate
	$(MAKE) migrate-test

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

lint: ## Стиль кода (PHP_CodeSniffer)
	$(PHP) vendor/bin/phpcs --standard=phpcs.xml.dist

lint-fix: ## Исправить стиль автоматически
	$(PHP) vendor/bin/phpcbf --standard=phpcs.xml.dist

stan: ## Статический анализ (PHPStan)
	$(PHP) vendor/bin/phpstan --memory-limit=-1 --no-progress

check: lint stan test ## Все проверки: стиль, анализ, тесты

migrate: ## Применить миграции
	$(PHP) php yii migrate --interactive=0

migrate-test: ## Применить миграции к тестовой базе
	$(PHP) php tests/Support/bin/yii migrate --interactive=0

db: ## MySQL-консоль
	$(DC) exec mysql sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

.PHONY: help init up down destroy build logs sh composer test coverage build-actors lint lint-fix stan check migrate migrate-test db
