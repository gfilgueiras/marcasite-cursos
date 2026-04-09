COMPOSE ?= docker compose
SERVICE_BACKEND ?= backend

.PHONY: dev/start dev/start-banner dev/test

# Dá down na stack, apaga docker/mysql/data, sobe Docker (MySQL, nginx, backend, frontend, Stripe), whsec, migrate e seed.
# Saída: passos + banner final com o link (sem ruído dos comandos).
dev/start:
	@bash scripts/dev-start.sh

# Só imprime o banner (usa FRONTEND_URL do .env se existir). Útil para testar o visual sem subir Docker.
dev/start-banner:
	@ONLY_BANNER=1 bash scripts/dev-start.sh

# Suite completa de testes (SQLite em memória, isolado do MySQL de desenvolvimento).
dev/test:
	$(COMPOSE) run --rm --no-deps \
		-e DB_CONNECTION=sqlite \
		-e DB_DATABASE=:memory: \
		$(SERVICE_BACKEND) php artisan test
