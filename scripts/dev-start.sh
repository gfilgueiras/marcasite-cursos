#!/usr/bin/env bash
# Sobe stack Docker, injeta whsec no .env (Stripe CLI), migrate, seed e reinicia o backend.
# Mostra o passo atual; detalhes dos comandos ficam ocultos; no fim, banner com o link do site.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export COMPOSE_FILE="${COMPOSE_FILE:-$ROOT/docker-compose.yml}"

Q() { "$@" >/dev/null 2>&1; }

step() {
  printf '  \033[90m•\033[0m %s\n' "$1"
}

MYSQL_DATA_DIR="${ROOT}/docker/mysql/data"

step "Parando containers Docker (libera o volume MySQL)…"
(docker compose --profile stripe down 2>/dev/null) || true

step "Apagando todo o conteúdo de docker/mysql/data…"
rm -rf "$MYSQL_DATA_DIR"
mkdir -p "$MYSQL_DATA_DIR"

if [[ ! -f "$ROOT/.env" ]]; then
  step "Criando .env a partir do exemplo…"
  cp "$ROOT/backend/.env.example" "$ROOT/.env"
fi

step "Obtendo segredo do webhook Stripe (whsec)…"
set +e
RAW_WHSEC="$(docker compose run --rm --no-deps -T stripe-cli listen --print-secret 2>/dev/null)"
SC_WHSEC=$?
set -e
WHSEC="$(echo -n "$RAW_WHSEC" | tr -d '\r\n' | grep -Eo 'whsec_[a-fA-F0-9]+' | head -1 || true)"
if [[ "$SC_WHSEC" -ne 0 ]] || [[ -z "$WHSEC" ]]; then
  WHSEC=""
fi

if [[ -n "${WHSEC:-}" ]]; then
  step "Atualizando STRIPE_SANDBOX_WEBHOOK_SECRET no .env…"
  if grep -q '^STRIPE_SANDBOX_WEBHOOK_SECRET=' "$ROOT/.env" 2>/dev/null; then
    if [[ "$(uname)" == "Darwin" ]]; then
      sed -i '' "s|^STRIPE_SANDBOX_WEBHOOK_SECRET=.*|STRIPE_SANDBOX_WEBHOOK_SECRET=${WHSEC}|" "$ROOT/.env"
    else
      sed -i "s|^STRIPE_SANDBOX_WEBHOOK_SECRET=.*|STRIPE_SANDBOX_WEBHOOK_SECRET=${WHSEC}|" "$ROOT/.env"
    fi
  else
    echo "STRIPE_SANDBOX_WEBHOOK_SECRET=${WHSEC}" >> "$ROOT/.env"
  fi
else
  step "Webhook Stripe: segredo não obtido (verifique STRIPE_SANDBOX_SECRET_KEY no .env)."
fi

step "Baixando imagens, construindo e subindo containers (MySQL, API, Nginx, frontend, Stripe)…"
Q docker compose --profile stripe up -d --build

step "Aguardando o MySQL ficar pronto…"
for _ in $(seq 1 45); do
  if Q docker compose exec -T mysql mysql -uaflow -paflow -e "SELECT 1" marcasite; then
    break
  fi
  sleep 1
done

step "Executando migrations…"
docker compose exec -T backend php artisan migrate --force >/dev/null 2>&1 || {
  echo "Erro ao executar migrations. Ver: docker compose logs backend" >&2
  exit 1
}

step "Executando seeders…"
docker compose exec -T backend php artisan db:seed --force >/dev/null 2>&1 || {
  echo "Erro ao executar seeders. Ver: docker compose logs backend" >&2
  exit 1
}

step "Limpando cache de configuração…"
Q docker compose exec -T backend php artisan config:clear

step "Reiniciando backend e nginx…"
Q docker compose restart backend nginx

SITE_URL="http://localhost:5173"
if [[ -f "$ROOT/.env" ]] && grep -q '^FRONTEND_URL=' "$ROOT/.env" 2>/dev/null; then
  SITE_URL="$(grep '^FRONTEND_URL=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r' | sed "s/^[\"']//;s/[\"']$//")"
  [[ -z "$SITE_URL" ]] && SITE_URL="http://localhost:5173"
fi

print_banner() {
  local url="$1"
  local u="$url"
  local inner=44
  if (( ${#u} > inner )); then
    u="${u:0:$((inner - 3))}..."
  fi
  printf '\n'
  printf '  \033[90m╭──────────────────────────────────────────────╮\033[0m\n'
  printf '  \033[90m│\033[0m  \033[1mMarcasite\033[0m — ambiente pronto                 \033[90m│\033[0m\n'
  printf '  \033[90m│\033[0m                                              \033[90m│\033[0m\n'
  printf '  \033[90m│\033[0m  Acesse o site:                              \033[90m│\033[0m\n'
  printf '  \033[90m│\033[0m  \033[36;1m%-*s\033[0m\033[90m│\033[0m\n' "$inner" "$u"
  printf '  \033[90m╰──────────────────────────────────────────────╯\033[0m\n'
  printf '\n'
}

print_banner "$SITE_URL"
