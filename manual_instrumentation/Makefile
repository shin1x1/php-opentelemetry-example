.PHONY: install
install: up
	docker compose run --rm php composer install


.PHONY: down-and-up
down-and-up: down up

.PHONY: up
up:
	docker compose up -d

.PHONY: down
down:
	docker compose down -v --remove-orphans
