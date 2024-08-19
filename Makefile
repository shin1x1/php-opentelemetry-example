.PHONY: install
install: up
	docker compose exec php-fpm composer install
	cd app && (test -f .env || cp -a .env.example .env && docker compose exec php-fpm php artisan key:generate)
	docker compose exec php-fpm php artisan migrate

.PHONY: up
up:
	docker compose up -d

.PHONY: down
down:
	docker compose down -v

.PHONY: restart
restart: down install

.PHONY: strace-php-fpm
strace-php-fpm:
	docker compose exec debug sh -c 'strace -f -s 1024 -y -ttT -p `pgrep pool`'
