# Event-Driven Orders

Proyecto de aprendizaje enfocado en **programación orientada a eventos**, **Docker** y **CI/CD**.

## Stack

| Capa         | Tecnología                    |
| ------------ | ----------------------------- |
| Backend      | Symfony 7 + Messenger         |
| Frontend     | React 18 + Vite (mínimo)      |
| Cola         | RabbitMQ (AMQP)               |
| Base de datos| PostgreSQL 16 + Doctrine ORM  |
| Tiempo real  | Mercure Hub (SSE)             |
| Contenedores | Docker + Docker Compose       |
| CI/CD        | GitHub Actions                |

## Estructura

```
├── backend/
│   ├── src/
│   │   ├── Controller/              # HTTP API (REST)
│   │   ├── Domain/
│   │   │   ├── Entity/             # Order (Doctrine ORM)
│   │   │   └── Service/            # CreateOrderService, OrderRepositoryInterface, DoctrineOrderRepository
│   │   ├── Messaging/
│   │   │   ├── Message/            # OrderCreatedMessage (DTO inmutable)
│   │   │   └── Handler/            # AbstractOrderHandler + 3 handlers especializados
│   │   └── Kernel.php
│   ├── config/                      # Symfony + Messenger + Doctrine
│   ├── migrations/                  # Doctrine migrations
│   ├── public/                      # Entry point
│   ├── docker-entrypoint.sh         # Auto-migraciones al iniciar
│   ├── Dockerfile                    # PHP-FPM + pgsql + amqp
│   └── nginx.conf                   # Reverse proxy
├── frontend/
│   ├── src/
│   │   └── main.jsx                 # App React con SSE
│   ├── tests/e2e/                   # Playwright E2E tests
│   ├── Dockerfile                    # Multi-stage (build + nginx)
│   └── nginx.conf                   # Proxy a API + Mercure
├── docker-compose.yml               # 8 servicios (nginx, php, postgres, rabbitmq, mercure, 3 workers, frontend)
└── .github/workflows/
    ├── ci.yml                       # Build, lint en PR/push
    └── cd.yml                       # Deploy automático a VPS
```

## Flujo de eventos (arquitectura actual)

1. Cliente hace `POST /api/orders`
2. Symfony crea la orden en PostgreSQL y **dispacha** `OrderCreatedMessage`
3. Messenger serializa y **fan-out** a 3 colas RabbitMQ (`async_notifications`, `async_inventory`, `async_analytics`)
4. Cada **worker** especializado consume su cola y ejecuta su handler
5. Cada handler publica el nuevo estado al **Mercure Hub** (tópico `orders/{id}`)
6. El **frontend** recibe la actualización en tiempo real por SSE

Esto es programación orientada a eventos: el controlador no sabe quién va a procesar la orden, solo dice "pasó esto". Los workers son independientes y escalables.

## Configuración (variables de entorno)

Las variables de entorno se cargan desde dos lugares:

- **`.env`** en la raíz del proyecto — leído por Docker Compose. Contiene credenciales de backing services (RabbitMQ, PostgreSQL) y el secreto JWT de Mercure.
- **`backend/.env`** — leído por Symfony. Contiene solo variables de Symfony (`APP_ENV`, `APP_SECRET`, `MERCURE_URL`, etc.).

Ambos archivos están en `.gitignore`. El repo incluye **`.env.example`** como plantilla pública con valores placeholder. En un entorno nuevo:

```bash
# 1. Copiar la plantilla y editar con valores reales
cp .env.example .env
# Generar un secreto JWT de 64 chars (256 bits mínimo, 512 bits recomendado):
openssl rand -hex 32
# Pegar el resultado en MERCURE_JWT_SECRET dentro de .env

# 2. Para Symfony, los valores dev de `backend/.env` son suficientes
# Si necesitás overrides locales, usá `backend/.env.local` (no se commitea)
```

> En producción, las credenciales deben venir de un secrets manager (Docker Secrets, Vault, AWS Secrets Manager), nunca del `.env` commiteado.

## Cómo levantarlo

```bash
# 1. Instalar dependencias del backend
cd backend
composer install
cd ..

# 2. Instalar dependencias del frontend
cd frontend
npm install
cd ..

# 3. Levantar todo con Docker
docker compose up -d --build

# 4. Verificar servicios
# API (HTTP):  http://localhost:8080/api/orders  (redirige a HTTPS)
# API (HTTPS): https://localhost:8443/api/orders  (cert self-signed)
# Frontend:    http://localhost:3000
# Mercure:     http://localhost:3001/.well-known/mercure
# RabbitMQ:    http://localhost:15673 (guest/guest)
# PostgreSQL:  docker exec edo_postgres psql -U orders -d orders

# 5. Ver logs de los workers
docker logs -f edo_worker_notifications
docker logs -f edo_worker_inventory
docker logs -f edo_worker_analytics
```

## API

| Método | Endpoint           | Descripción                        |
| ------ | ------------------ | ---------------------------------- |
| POST   | `/api/orders`      | Crear orden (dispara evento async) |
| GET    | `/api/orders`      | Listar órdenes                     |
| GET    | `/api/orders/{id}` | Obtener una orden                  |

### Ejemplo de creación

```bash
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{"customerEmail":"test@example.com","items":["Pizza","Coke"],"total":25.50}'
```

## Roadmap de fases

### ✅ Fase 1: Eventos de dominio con Messenger + Redis

Eventos async con worker separado usando Redis Streams. *(Nota: el transporte actual es RabbitMQ desde la Fase 5.)*

### ✅ Fase 2: Múltiples workers especializados

Tres workers independientes, cada uno con su propia cola:

- `worker_notifications` → envía confirmación por email
- `worker_inventory` → actualiza stock
- `worker_analytics` → registra métricas

El message bus hace fan-out: un solo `OrderCreatedMessage` se enruta a las 3 colas. *(Nota: originalmente usaba Redis, migrado a RabbitMQ en Fase 5.)*

### ✅ Fase 3: Tiempo real con Mercure

Mercure Hub notifica al frontend por SSE cuando una orden cambia de estado.
Cada handler publica updates al tópico `orders/{id}` después de procesar.

### ✅ Fase 4: Persistencia con PostgreSQL + Doctrine

Reemplazo de SQLite por PostgreSQL 16 con Doctrine ORM.
Entidad `Order` mapeada, migraciones automáticas al levantar los contenedores.

### ✅ Fase 5: RabbitMQ

Migración del transporte de Redis a RabbitMQ (AMQP). Workers consumen de colas AMQP con rabbitmq:4-management.

### ✅ Fase 6: Testing

- ✅ Unit tests: Order, OrderCreatedMessage, CreateOrderService (14 tests, 58 assertions)
- ✅ Integration tests: repositorio + handlers con SQLite in-memory (8 tests, 25 assertions)
- ✅ Functional tests: OrderController HTTP end-to-end (6 tests)
- ✅ E2E con Playwright (2 tests: crear orden + ver SSE en vivo, carga inicial de página)

### ✅ Fase 7: Producción

- ✅ 7.1 — Centralizar secreto JWT de Mercure (`.env` raíz, sin hardcodeo)
- ✅ 7.2 — Hardening de Mercure (quitar anonymous, JWT por endpoint, CORS restringido)
- ✅ 7.3 — Security headers en Nginx (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, CSP)
- ✅ 7.4 — HTTPS con certificado self-signed (`certs/cert.pem` + `certs/key.pem`, nginx en :8443)
- ✅ 7.5 — Higiene de variables de entorno (`.env.example` template, parametrización en `docker-compose.yml`)
- ✅ 7.6 — CD workflow alineado con la arquitectura real (3 workers + RabbitMQ)

### ✅ Fase 8: Deuda técnica

- ✅ 8.1 — Idempotencia en handlers (columna `processed_by` JSON en Order, cada handler registra su paso, retries se saltan)
- ✅ 8.2 — Refactor DRY de handlers (Template Method: `AbstractOrderHandler`, 4 hooks por handler, ~30 líneas cada uno)

### ✅ Fase 9: Validación y DTOs

- ✅ 9.1 — DTO tipado para creación de órdenes (`CreateOrderRequest` con constraints Symfony Validator: `NotBlank`, `Email`, `NotNull`, `Count`, `GreaterThan`)
- ✅ 9.1 — Validación de entrada en `OrderController::create()` con respuesta estructurada 400 campo por campo (`{errors: {customerEmail: ["..."], items: ["..."]}}`)
- ✅ 9.1 — Manejo estructurado de errores: JSON inválido devuelve 400 con `{errors: {_body: ["Invalid JSON body"]}}`; violaciones de validación devuelven 400 con mensajes por campo

## CI/CD

- **CI** (`.github/workflows/ci.yml`): Se ejecuta en cada push/PR. Valida build de backend, frontend y Docker. **Nota: no ejecuta phpunit**, solo build y lint.
- **CD** (`.github/workflows/cd.yml`): Deploy automático a VPS via SSH cuando se mergea a `main`.

Configurá estos secrets en tu repo:

- `SSH_PRIVATE_KEY`
- `SSH_USER`
- `SSH_HOST`

## Deuda técnica conocida

1. **`float $total` para dinero**: Causa problemas de precisión en coma flotante. Necesita un value object Money.
2. **Condición de carrera en `processedBy`**: La columna JSON puede perder datos cuando múltiples handlers guardan concurrentemente (last write wins). La idempotencia individual funciona, pero `processedBy` no refleja todos los handlers que corrieron. Fix: optimistic locking o tabla `order_handler_status`.
3. **CI sin tests**: El pipeline de CI solo hace build y lint, no ejecuta `phpunit`.

---

Frontend intencionalmente mínimo: el foco está en backend, eventos, contenedores y despliegue.