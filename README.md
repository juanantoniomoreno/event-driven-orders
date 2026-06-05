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
│   │   ├── Controller/          # HTTP API (REST)
│   │   ├── Entity/              # Order (Doctrine ORM)
│   │   ├── Repository/          # OrderRepository (Doctrine)
│   │   ├── Service/             # CreateOrderService
│   │   └── MessageHandler/      # 3 handlers especializados
│   ├── config/                  # Symfony + Messenger + Doctrine
│   ├── migrations/              # Doctrine migrations
│   ├── public/                  # Entry point
│   ├── docker-entrypoint.sh     # Auto-migraciones al iniciar
│   ├── Dockerfile               # PHP-FPM + pgsql + amqp
│   └── nginx.conf               # Reverse proxy
├── frontend/
│   ├── src/
│   │   └── main.jsx             # App React con SSE
│   ├── Dockerfile               # Multi-stage (build + nginx)
│   └── nginx.conf               # Proxy a API + Mercure
├── docker-compose.yml           # 8 servicios (nginx, php, postgres, rabbitmq, mercure, 3 workers, frontend)
└── .github/workflows/
    ├── ci.yml                   # Build, lint, test en PR/push
    └── cd.yml                   # Deploy automático a VPS (⚠️ desactualizado)
```

## Flujo de eventos (arquitectura actual)

1. Cliente hace `POST /api/orders`
2. Symfony crea la orden en PostgreSQL y **dispacha** `OrderCreatedMessage`
3. Messenger serializa y **fan-out** a 3 colas RabbitMQ (`async_notifications`, `async_inventory`, `async_analytics`)
4. Cada **worker** especializado consume su cola y ejecuta su handler
5. Cada handler publica el nuevo estado al **Mercure Hub** (tópico `orders/{id}`)
6. El **frontend** recibe la actualización en tiempo real por SSE

Esto es programación orientada a eventos: el controlador no sabe quién va a procesar la orden, solo dice "pasó esto". Los workers son independientes y escalables.

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
# API:        http://localhost:8080/api/orders
# Frontend:   http://localhost:3000
# Mercure:    http://localhost:3001/.well-known/mercure
# RabbitMQ:   http://localhost:15672 (guest/guest)
# PostgreSQL: docker exec edo_postgres psql -U app -d orders

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

Eventos async con worker separado usando Redis Streams.

### ✅ Fase 2: Múltiples workers especializados

Tres workers independientes, cada uno con su propia cola en Redis:

- `worker_notifications` → envía confirmación por email
- `worker_inventory` → actualiza stock
- `worker_analytics` → registra métricas

El message bus hace fan-out: un solo `OrderCreatedMessage` se enruta a las 3 colas.

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
- ⬜ E2E con Playwright

### 🔄 Fase 7: Producción (en progreso)

- ✅ 7.1 — Centralizar secreto JWT de Mercure (`.env` raíz, sin hardcodeo)
- ✅ 7.2 — Hardening de Mercure (quitar anonymous, JWT por endpoint, CORS restringido)
- ✅ 7.3 — Security headers en Nginx (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, CSP)
- 🔲 7.4 — HTTPS con certificado self-signed
- 🔲 7.5 — Higiene de variables de entorno

## CI/CD

- **CI** (`.github/workflows/ci.yml`): Se ejecuta en cada push/PR. Valida build de backend, frontend y Docker.
- **CD** (`.github/workflows/cd.yml`): Deploy automático a VPS via SSH cuando se mergea a `main`.

Configurá estos secrets en tu repo:

- `SSH_PRIVATE_KEY`
- `SSH_USER`
- `SSH_HOST`

---

Frontend intencionalmente mínimo: el foco está en backend, eventos, contenedores y despliegue.
