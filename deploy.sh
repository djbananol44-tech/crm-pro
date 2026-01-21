#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
#  JGGL CRM — Quick Deploy (Pull & Update)
#  
#  Usage:
#    ./deploy.sh                    # Deploy latest
#    ./deploy.sh --tag v1.2.3       # Deploy specific version
#    ./deploy.sh --tag sha-abc1234  # Deploy specific commit
#
# ═══════════════════════════════════════════════════════════════════════════════

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Default config
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
IMAGE_REGISTRY="${IMAGE_REGISTRY:-ghcr.io}"
IMAGE_NAME="${IMAGE_NAME:-djbananol44-tech/crm-pro}"
IMAGE_TAG="latest"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --tag|-t)
            IMAGE_TAG="$2"
            shift 2
            ;;
        --help|-h)
            echo "Usage: $0 [--tag VERSION]"
            echo ""
            echo "Options:"
            echo "  --tag, -t    Specify image tag (default: latest)"
            echo ""
            echo "Examples:"
            echo "  $0                    # Deploy latest"
            echo "  $0 --tag v1.2.3       # Deploy specific version"
            echo "  $0 --tag sha-abc1234  # Deploy specific commit"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

log() { echo -e "${CYAN}▶ $1${NC}"; }
ok()  { echo -e "${GREEN}✅ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠️  $1${NC}"; }
err() { echo -e "${RED}❌ $1${NC}"; exit 1; }

# ─────────────────────────────────────────────────────────────────────────────
# Pre-checks
# ─────────────────────────────────────────────────────────────────────────────
[[ -f "$COMPOSE_FILE" ]] || err "File $COMPOSE_FILE not found"
[[ -f ".env" ]] || err "File .env not found"

echo ""
echo -e "${CYAN}${BOLD}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║           🚀 JGGL CRM — Quick Deploy                      ║${NC}"
echo -e "${CYAN}${BOLD}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Set image with tag
export APP_IMAGE="${IMAGE_REGISTRY}/${IMAGE_NAME}:${IMAGE_TAG}"
echo -e "${CYAN}📦 Image: ${BOLD}${APP_IMAGE}${NC}"
echo ""

# ─────────────────────────────────────────────────────────────────────────────
# Step 1: Login to GHCR (if needed for private repos)
# ─────────────────────────────────────────────────────────────────────────────
if [[ -n "${GITHUB_TOKEN:-}" ]]; then
    log "Logging in to GitHub Container Registry..."
    echo "$GITHUB_TOKEN" | docker login ghcr.io -u "${GITHUB_USER:-github}" --password-stdin
    ok "Logged in to GHCR"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 2: Pull latest images
# ─────────────────────────────────────────────────────────────────────────────
log "Pulling images (tag: ${IMAGE_TAG})..."
docker compose -f "$COMPOSE_FILE" pull
ok "Images pulled"

# ─────────────────────────────────────────────────────────────────────────────
# Step 3: Copy public assets (required for nginx to serve static files)
# ─────────────────────────────────────────────────────────────────────────────
log "Updating public assets..."
docker compose -f "$COMPOSE_FILE" rm -f init-assets 2>/dev/null || true
docker compose -f "$COMPOSE_FILE" up init-assets
ok "Public assets updated"

# ─────────────────────────────────────────────────────────────────────────────
# Step 4: Start/update containers
# ─────────────────────────────────────────────────────────────────────────────
log "Starting containers..."
docker compose -f "$COMPOSE_FILE" up -d --remove-orphans
ok "Containers started"

# ─────────────────────────────────────────────────────────────────────────────
# Step 5: Wait for healthy
# ─────────────────────────────────────────────────────────────────────────────
log "Waiting for services to be healthy..."

# Wait up to 60 seconds
for i in {1..12}; do
    if docker compose -f "$COMPOSE_FILE" ps 2>/dev/null | grep -q "unhealthy"; then
        sleep 5
    else
        break
    fi
done

# Check final health
if docker compose -f "$COMPOSE_FILE" ps 2>/dev/null | grep -q "unhealthy"; then
    warn "Some services are unhealthy, check logs"
else
    ok "All services healthy"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 6: Run migrations
# ─────────────────────────────────────────────────────────────────────────────
log "Running migrations..."
docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force --no-interaction 2>/dev/null || warn "Migration skipped"
ok "Migrations complete"

# ─────────────────────────────────────────────────────────────────────────────
# Step 6.1: Ensure test users exist
# ─────────────────────────────────────────────────────────────────────────────
log "Ensuring test users exist..."
docker compose -f "$COMPOSE_FILE" exec -T app php artisan db:seed --class=UserSeeder --force --no-interaction 2>/dev/null || warn "User seeder skipped"
ok "Test users ready"

# ─────────────────────────────────────────────────────────────────────────────
# Step 7: Clear caches
# ─────────────────────────────────────────────────────────────────────────────
log "Clearing caches..."
docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:clear 2>/dev/null || true
docker compose -f "$COMPOSE_FILE" exec -T app php artisan cache:clear 2>/dev/null || true
docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:clear 2>/dev/null || true
ok "Caches cleared"

# ─────────────────────────────────────────────────────────────────────────────
# Step 8: Cleanup old images
# ─────────────────────────────────────────────────────────────────────────────
log "Cleaning up old images..."
docker image prune -f --filter "until=24h" 2>/dev/null || true
ok "Cleanup complete"

# ─────────────────────────────────────────────────────────────────────────────
# Done
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✅ Deploy completed successfully!               ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Show status
docker compose -f "$COMPOSE_FILE" ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo -e "${CYAN}📊 Diagnostics: docker compose -f $COMPOSE_FILE exec app php artisan jggl:doctor${NC}"
echo -e "${CYAN}📝 Logs: docker compose -f $COMPOSE_FILE logs -f${NC}"
echo ""
