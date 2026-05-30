.PHONY: help setup setup-prod start stop build migrate status clean

# Colors for terminal styling
YELLOW := \033[33m
GREEN  := \033[32m
CYAN   := \033[36m
RESET  := \033[0m

help:
	@echo "$(CYAN)ServerWatch Development Makefile$(RESET)"
	@echo "--------------------------------"
	@echo "$(YELLOW)Usage:$(RESET) make [target]"
	@echo ""
	@echo "$(YELLOW)Available Targets:$(RESET)"
	@echo "  $(GREEN)setup$(RESET)        Install all dependencies (Docker images, Composer, npm)"
	@echo "  $(GREEN)setup-prod$(RESET)   Bootstrap production deployment + Nginx configuration"
	@echo "  $(GREEN)start$(RESET)        Start Docker stack and local dev servers (Laravel API, Vue UI)"
	@echo "  $(GREEN)stop$(RESET)         Stop local dev servers and shut down Docker stack"
	@echo "  $(GREEN)build$(RESET)        Build Vue UI production static files"
	@echo "  $(GREEN)migrate$(RESET)      Run database migrations inside Laravel API"
	@echo "  $(GREEN)status$(RESET)       Check active Docker containers and server ports"
	@echo "  $(GREEN)clean$(RESET)        Clear caches, delete node_modules and vendor directories"

setup:
	@echo "$(YELLOW)=== Step 1: Pulling Docker Monitoring Stack ===$(RESET)"
	docker compose -f infra/docker-compose.yml pull
	@echo "$(YELLOW)=== Step 2: Bootstrapping Laravel API Backend ===$(RESET)"
	cd monitor-api && cp -n .env.example .env || true
	cd monitor-api && composer install
	cd monitor-api && php artisan key:generate --ansi
	cd monitor-api && php artisan vendor:publish --tag="health-config" --force
	cd monitor-api && php artisan vendor:publish --tag="health-migrations" --force
	cd monitor-api && php artisan migrate --force
	@echo "$(YELLOW)=== Step 3: Bootstrapping Vue UI Frontend ===$(RESET)"
	cd monitor-ui && cp -n .env.example .env || true
	cd monitor-ui && npm install
	@echo "$(GREEN)✓ Setup complete! Run 'make start' to launch the stack.$(RESET)"

setup-prod:
	@echo "$(YELLOW)=== Step 1: Pulling Docker Monitoring Stack ===$(RESET)"
	docker compose -f infra/docker-compose.yml pull
	@echo "$(YELLOW)=== Step 2: Bootstrapping Laravel API Backend (Prod) ===$(RESET)"
	cd monitor-api && cp -n .env.example .env || true
	cd monitor-api && composer install --no-dev --optimize-autoloader
	cd monitor-api && php artisan key:generate --ansi
	cd monitor-api && php artisan vendor:publish --tag="health-config" --force
	cd monitor-api && php artisan vendor:publish --tag="health-migrations" --force
	cd monitor-api && php artisan migrate --force
	@echo "$(YELLOW)=== Step 3: Bootstrapping Vue UI Frontend (Prod) ===$(RESET)"
	cd monitor-ui && cp -n .env.example .env || true
	cd monitor-ui && npm install
	cd monitor-ui && npm run build
	@echo "$(YELLOW)=== Step 4: Configuring Nginx Reverse Proxy ===$(RESET)"
	sudo cp infra/nginx/serverwatch.conf /etc/nginx/sites-available/serverwatch
	sudo ln -sf /etc/nginx/sites-available/serverwatch /etc/nginx/sites-enabled/serverwatch
	sudo nginx -t && sudo systemctl reload nginx
	@echo "$(GREEN)✓ Production setup complete! Served at http://YOUR_SERVER_IP/dashboard$(RESET)"

start: stop
	@echo "$(YELLOW)=== Starting Docker Stack (Prometheus, Grafana, Uptime Kuma) ===$(RESET)"
	docker compose -f infra/docker-compose.yml up -d
	@echo "$(YELLOW)=== Starting Laravel API (http://localhost:8000) ===$(RESET)"
	cd monitor-api && php artisan serve --port=8000 > /dev/null 2>&1 &
	@echo "$(YELLOW)=== Starting Vue 3 UI (http://localhost:5173) ===$(RESET)"
	cd monitor-ui && npm run dev -- --port 5173 --host 0.0.0.0 > /dev/null 2>&1 &
	@sleep 2
	@echo "$(GREEN)✓ Stack is active!$(RESET)"
	@echo "  - $(CYAN)Vue 3 UI:$(RESET)      http://localhost:5173"
	@echo "  - $(CYAN)Laravel API:$(RESET)   http://localhost:8000/api/v1/servers"
	@echo "  - $(CYAN)Prometheus:$(RESET)    http://localhost:9091"
	@echo "  - $(CYAN)Grafana:$(RESET)       http://localhost:3010"
	@echo "  - $(CYAN)Uptime Kuma:$(RESET)   http://localhost:3001"
	@echo "  - $(CYAN)Alertmanager:$(RESET)  http://localhost:9093"

stop:
	@echo "$(YELLOW)=== Stopping Laravel API & Vue UI Servers ===$(RESET)"
	@lsof -t -i :8000 | xargs kill -9 2>/dev/null || true
	@lsof -t -i :5173 | xargs kill -9 2>/dev/null || true
	@echo "$(YELLOW)=== Stopping Docker Containers ===$(RESET)"
	docker compose -f infra/docker-compose.yml down
	@echo "$(GREEN)✓ All services stopped.$(RESET)"

build:
	@echo "$(YELLOW)=== Building Vue 3 UI for production ===$(RESET)"
	cd monitor-ui && npm run build
	@echo "$(GREEN)✓ Build finished. Dist files saved to monitor-ui/dist/$(RESET)"

migrate:
	@echo "$(YELLOW)=== Running Laravel migrations ===$(RESET)"
	cd monitor-api && php artisan migrate --force

status:
	@echo "$(YELLOW)=== Docker Container Status ===$(RESET)"
	docker compose -f infra/docker-compose.yml ps
	@echo ""
	@echo "$(YELLOW)=== Local Port Allocation ===$(RESET)"
	@echo "Port 8000 (API):      $$(lsof -i :8000 -t | wc -l | tr -d ' ') process(es)"
	@echo "Port 5173 (Vue UI):   $$(lsof -i :5173 -t | wc -l | tr -d ' ') process(es)"

clean:
	@echo "$(YELLOW)=== Cleaning Cache & Node Modules ===$(RESET)"
	cd monitor-api && php artisan config:clear && php artisan cache:clear || true
	rm -rf monitor-ui/node_modules monitor-ui/dist
	rm -rf monitor-api/vendor
	@echo "$(GREEN)✓ Cleanup complete. Run 'make setup' again to reinstall.$(RESET)"
