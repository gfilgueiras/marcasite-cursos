# Marcasite Cursos

Plataforma de **catálogo de cursos**, **inscrições de alunos** e **pagamentos** integrados ao **Stripe**, com **área administrativa** (usuários, cursos, inscrições, exportação) e **frontend** em Vue 3.

---

## Visão geral

| Camada      | Tecnologia                                                        |
| ----------- | ----------------------------------------------------------------- |
| API         | Laravel 11, PHP 8.2+, Laravel Sanctum                             |
| Banco       | MySQL 8 (Docker)                                                  |
| Frontend    | Vue 3, Vue Router, Vite 6, Bootstrap 5                            |
| Pagamentos  | Stripe (modo sandbox ou live), webhooks                           |
| Infra local | Docker Compose (Nginx, PHP-FPM, Node, MySQL, Stripe CLI opcional) |

A API REST está versionada em `/api/v1`. O Nginx expõe a aplicação Laravel em **`http://localhost:8080`**; o Vite serve o frontend em **`http://localhost:5173`**.

---

## Estrutura do repositório

```
marcasite-cursos/
├── backend/          # Laravel (API, models, migrations, testes PHPUnit)
├── frontend/         # Vue 3 + Vite (SPA)
├── docker/           # Dockerfiles (nginx, php, mysql) e configs
├── scripts/          # Automação (ex.: dev-start.sh)
├── db/               # Volume/dados MySQL (referência; dados em docker/mysql/data)
├── docker-compose.yml
├── Makefile
└── .env              # Raiz: montado nos containers backend e frontend (criar a partir dos exemplos)
```

---

## Pré-requisitos

- **Docker**
- **Make** (opcional, mas recomendado para os atalhos do projeto)

---

## Primeiros passos

1. **Criar e configurar o `.env` na raiz**
   - Copie o arquivo de exemplo: `cp .env.example .env`. Se não existir `.env.example` na raiz, use `cp backend/.env.example .env`.
   - Edite o `.env`: ajuste **`FRONTEND_URL`** e **`API_URL`** (por exemplo `http://localhost:5173` e `http://localhost:8080`), confira usuário e senha do MySQL conforme o `docker-compose.yml` e, se for usar pagamentos em teste, preencha as chaves **`STRIPE_SANDBOX_*`** (veja também `backend/.env.example` e `frontend/.env.example`).
   - Garanta que o Laravel tenha **`APP_KEY`** definido (se estiver vazio, após subir os containers: `docker compose exec backend php artisan key:generate`).

2. **Subir o ambiente de desenvolvimento**

   ```bash
   make dev/start
   ```

   O script sobe Docker, aplica migrations e seeders, e no final exibe um **banner** com o endereço do site.

3. **Acessar no navegador**
   - Use a URL mostrada no banner (por padrão o frontend em **`http://localhost:5173`**). A API REST fica em **`http://localhost:8080`** (`/api/v1/...`).

---

## Comandos do Makefile

### `make dev/start`

Executa `scripts/dev-start.sh`, que:

1. Dá **down** na stack (incluindo profile `stripe`) para liberar o volume MySQL
2. **Apaga** o conteúdo de `docker/mysql/data` e recria a pasta (banco zerado a cada execução)
3. Se não existir `.env` na raiz, copia `backend/.env.example` para `.env`
4. Tenta obter o segredo de webhook Stripe (`whsec_...`) via container **stripe-cli** e atualiza `STRIPE_SANDBOX_WEBHOOK_SECRET` no `.env`
5. Sobe os containers com `docker compose --profile stripe up -d --build` (MySQL, backend PHP, Nginx, frontend Node, Stripe CLI)
6. Aguarda o MySQL, roda **`php artisan migrate --force`** e **`php artisan db:seed --force`**
7. Limpa cache de config do Laravel e reinicia **backend** e **nginx**
8. Exibe um **banner** com a URL do site (usa `FRONTEND_URL` do `.env`, senão `http://localhost:5173`)

```bash
make dev/start
```

### `make dev/start-banner`

Só imprime o banner (sem subir Docker). Útil para conferir a URL configurada em `FRONTEND_URL`.

```bash
make dev/start-banner
```

### `make dev/test`

Roda a **suíte completa de testes PHPUnit** dentro do container `backend`, com **SQLite em memória** (isolado do MySQL de desenvolvimento):

```bash
make dev/test
```

Equivalente a:

```bash
docker compose run --rm --no-deps \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: \
  backend php artisan test
```

---

## Configuração de ambiente (`.env` na raiz)

1. Copie o exemplo da raiz (se existir) ou use o backend como base:

   ```bash
   cp .env.example .env
   # ou, se ainda não tiver .env:
   cp backend/.env.example .env
   ```

2. O **`docker-compose.yml`** monta `./.env` em **backend** e **frontend** como somente leitura. Variáveis úteis na raiz (ver também `backend/.env.example` e `frontend/.env.example`):
   - **`API_URL`** / **`FRONTEND_URL`** — URLs usadas pelo projeto (o script de dev lê `FRONTEND_URL` para o banner)
   - **MySQL** — credenciais alinhadas ao `docker-compose` (`marcasite`, usuário `aflow`, etc.)
   - **Stripe** — `STRIPE_MODE` (`sandbox` ou `live`), chaves `STRIPE_SANDBOX_*` / `STRIPE_LIVE_*`, e `STRIPE_SANDBOX_WEBHOOK_SECRET` (o `make dev/start` tenta preencher automaticamente com o Stripe CLI)

3. No **frontend**, o Vite usa normalmente **`VITE_API_URL`** apontando para a API (ex.: `http://localhost:8080`). Veja `frontend/.env.example`.

4. Gere a chave da aplicação Laravel se necessário (dentro do container backend):

   ```bash
   docker compose exec backend php artisan key:generate
   ```

---

## Serviços Docker (resumo)

| Serviço        | Função                                                                                                                             |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **mysql**      | MySQL 8, porta host `3306`, dados em `docker/mysql/data`                                                                           |
| **backend**    | PHP-FPM + código em `./backend`                                                                                                    |
| **nginx**      | HTTP na porta **`8080`** → `backend` via FastCGI                                                                                   |
| **frontend**   | Node 20, `npm install` + `npm run dev` na porta **`5173`**                                                                         |
| **stripe-cli** | Profile **`stripe`**: encaminha webhooks para `http://nginx/api/v1/webhooks/stripe` (requer `STRIPE_SANDBOX_SECRET_KEY` no `.env`) |

Para subir só o Stripe CLI manualmente (sem o script completo):

```bash
docker compose --profile stripe run --rm -it stripe-cli
```

**Importante:** dentro do Docker, o forward do Stripe CLI usa **`http://nginx/...`**, não `localhost:8080`, porque o tráfego é entre containers.

---

## API REST (`/api/v1`)

Principais rotas (prefixo base: `http://localhost:8080/api/v1`):

| Método | Caminho                     | Descrição                                                 |
| ------ | --------------------------- | --------------------------------------------------------- |
| POST   | `/auth/login`               | Login (Sanctum)                                           |
| POST   | `/auth/logout`              | Logout (requer `auth:sanctum`)                            |
| GET    | `/courses`                  | Lista cursos (público)                                    |
| POST   | `/enrollments`              | Nova inscrição / checkout                                 |
| POST   | `/my-enrollments`           | Consulta inscrições por dados do aluno (throttle)         |
| POST   | `/webhooks/stripe`          | Webhook Stripe                                            |
| \*     | `/admin/users`              | CRUD usuários (admin)                                     |
| \*     | `/admin/courses`            | CRUD cursos (admin)                                       |
| GET    | `/admin/enrollments`        | Lista inscrições (admin)                                  |
| GET    | `/admin/enrollments/export` | Exportação (autenticado + middleware admin)               |
| \*     | `/admin/enrollments/{id}`   | Detalhe / atualização / exclusão conforme rotas definidas |

Rotas sensíveis de admin podem exigir **`auth:sanctum`** e middleware **`admin`**. Consulte `backend/routes/api.php` para a lista exata.

---

## Frontend (rotas Vue)

| Rota                  | Descrição                |
| --------------------- | ------------------------ |
| `/`                   | Dashboard                |
| `/courses`            | Catálogo de cursos       |
| `/meus-cursos`        | Cursos do usuário        |
| `/gerenciar-cursos`   | Gestão de cursos (admin) |
| `/usuarios`           | Usuários (admin)         |
| `/courses/:id/enroll` | Inscrição em um curso    |
| `/enrollment/success` | Pós-inscrição            |
| `/admin/login`        | Login administrativo     |
| `/admin/inscricoes`   | Inscrições (admin)       |
| `/configuracoes`      | Placeholder              |

---

## Testes automatizados

Além de `make dev/test`, você pode executar testes manualmente no container:

```bash
docker compose exec backend php artisan test
```

(Recomenda-se `make dev/test` para não depender do MySQL local do compose.)

---

## Licença

Defina conforme a política do seu projeto; o esqueleto Laravel no `backend/` segue a licença MIT do framework (ver `backend/README.md` padrão do Laravel).
