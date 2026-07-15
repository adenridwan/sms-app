# ===========================================
# SMS Enterprise - Makefile
# ===========================================

.PHONY: help build up down restart logs shell composer artisan migrate seed fresh test horizon queue npm dev prod

# Colors
GREEN=\033[0;32m
YELLOW=\033[0;33m
NC=\033[0m

# Default
help:
	@echo "$(GREEN)SMS Enterprise - Available Commands$(NC)"
	@echo ""
	@echo "$(YELLOW)Docker Commands:$(NC)"
	@echo "  make build         - Build all containers"
	@echo "  make up            - Start all containers"
	@echo "  make down          - Stop all containers"
	@echo "  make restart       - Restart all containers"
	@echo "  make logs          - View container logs"
	@echo "  make ps            - List running containers"
	@echo ""
	@echo "$(YELLOW)Development:$(NC)"
	@echo "  make dev           - Start with development services"
	@echo "  make dev-full      - Start with all dev services (mail, storage)"
	@echo ""
	@echo "$(YELLOW)Shell Access:$(NC)"
	@echo "  make shell         - Access PHP container shell"
	@echo "  make shell-postgres- Access PostgreSQL shell"
	@echo "  make shell-redis   - Access Redis CLI"
	@echo ""
	@echo "$(YELLOW)Laravel Commands:$(NC)"
	@echo "  make composer      - Run composer (use: make composer c='install')"
	@echo "  make artisan       - Run artisan (use: make artisan c='migrate')"
	@echo "  make migrate       - Run migrations"
	@echo "  make seed          - Run seeders"
	@echo "  make fresh         - Fresh migration with seed"
	@echo "  make test          - Run tests"
	@echo "  make horizon       - Start Horizon"
	@echo "  make queue         - Start queue worker"
	@echo ""
	@echo "$(YELLOW)Frontend:$(NC)"
	@echo "  make npm           - Run npm (use: make npm c='install')"
	@echo "  make assets        - Build frontend assets"

# ===========================================
# Docker Commands
# ===========================================
build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

logs:
	docker-compose logs -f

ps:
	docker-compose ps

# ===========================================
# Development
# ===========================================
dev:
	docker-compose --profile dev up -d

dev-full:
	docker-compose --profile dev --profile storage up -d

# ===========================================
# Shell Access
# ===========================================
shell:
	docker-compose exec php sh

shell-postgres:
	docker-compose exec postgres psql -U sms_user -d sms_db

shell-redis:
	docker-compose exec redis redis-cli -a sms_redis_secret

# ===========================================
# Laravel Commands
# ===========================================
composer:
	docker-compose exec php composer $(c)

artisan:
	docker-compose exec php php artisan $(c)

migrate:
	docker-compose exec php php artisan migrate

seed:
	docker-compose exec php php artisan db:seed

fresh:
	docker-compose exec php php artisan migrate:fresh --seed

test:
	docker-compose exec php php artisan test

horizon:
	docker-compose exec php php artisan horizon

queue:
	docker-compose exec php php artisan queue:work

# ===========================================
# Frontend
# ===========================================
npm:
	docker-compose exec node npm $(c)

assets:
	docker-compose exec node npm run build

# ===========================================
# Initialization
# ===========================================
init:
	@echo "$(GREEN)Initializing SMS Enterprise...$(NC)"
	cp .env.example .env
	docker-compose build
	docker-compose up -d
	docker-compose exec php composer install
	docker-compose exec php php artisan key:generate
	docker-compose exec php php artisan migrate --seed
	docker-compose exec php php artisan storage:link
	@echo "$(GREEN)Done! Access at http://localhost$(NC)"

# ===========================================
# Cleanup
# ===========================================
clean:
	docker-compose down -v --rmi local
	rm -rf backend/vendor
	rm -rf backend/node_modules
