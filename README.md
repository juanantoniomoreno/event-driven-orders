# Event-Driven Orders

Proyecto de aprendizaje enfocado en **programación orientada a eventos**, **Docker** y **CI/CD**.

## Stack

| Capa         | Tecnología                    |
| ------------ | ----------------------------- |
| Backend      | Symfony 7 + Messenger (Redis) |
| Frontend     | React 18 + Vite (mínimo)      |
| Cola         | Redis Streams                 |
| Contenedores | Docker + Docker Compose       |
| CI/CD        | GitHub Actions                |

## Estructura

```
├── backend/
│   ├── src/
│   │   ├── Controller/          # HTTP API (REST)
│   │   ├── Domain/
│   │   │   ├── Entity/          # Order (modelo de dominio)
│   │   │   └── Service/         # Lógica de negocio + repositorio
│   │   └── Messaging/
│   │       ├── Message/         # OrderCreatedMessage (para Messenger)
│   │       └── Handler/         # Procesadores async
│   ├── config/                  # Symfony config
│   ├── public/                  # Entry point
│   ├── Dockerfile               # PHP-FPM + Redis extension
│   └── nginx.conf               # Reverse proxy
├── frontend/
│   ├── src/
│   │   └── main.jsx             # App React mínima
│   ├── Dockerfile               # Multi-stage (build + nginx)
│   └── nginx.conf               # Proxy a API
├── docker-compose.yml           # Redis + PHP + Nginx + Worker + Frontend
└── .github/workflows/
    ├── ci.yml                   # Build, lint, test en PR/push
    └── cd.yml                   # Deploy automático a VPS
```

## Flujo de eventos (Fase 1)

1. Cliente hace `POST /api/orders`
2. Symfony crea la orden y **dispacha** `OrderCreatedMessage`
3. Messenger la serializa y la **encola en Redis**
4. El **worker** (`messenger:consume async`) la recoge y ejecuta el handler
5. El handler simula procesamiento (sleep + log)

Esto es programación orientada a eventos: el controlador no sabe quién va a procesar la orden, solo dice "pasó esto".

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
# Redis:      docker exec edo_redis redis-cli ping

# 5. Ver logs del worker (procesamiento async)
docker logs -f edo_worker
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

Lo que ya está armado. Eventos async con worker separado.

### ⬜ Fase 2: Múltiples workers especializados

Separar handlers en distintos consumers:

- `notifications` (email)
- `inventory` (stock)
- `analytics` (métricas)

### ⬜ Fase 3: Tiempo real

Agregar Mercure o SSE para notificar al frontend cuando una orden cambia de estado.

### ⬜ Fase 4: Persistencia real

Reemplazar el repositorio en memoria por Doctrine + PostgreSQL.

### ⬜ Fase 5: RabbitMQ

Migrar el transport de Redis a RabbitMQ para aprender AMQP.

### ⬜ Fase 6: Testing

- Unit tests con PHPUnit
- Integration tests para handlers
- E2E con Playwright

### ⬜ Fase 7: Producción

- HTTPS con Let's Encrypt
- Secrets en GitHub
- Docker Swarm o AWS ECS

## CI/CD

- **CI** (`.github/workflows/ci.yml`): Se ejecuta en cada push/PR. Valida build de backend, frontend y Docker.
- **CD** (`.github/workflows/cd.yml`): Deploy automático a VPS via SSH cuando se mergea a `main`.

Configurá estos secrets en tu repo:

- `SSH_PRIVATE_KEY`
- `SSH_USER`
- `SSH_HOST`

---

Frontend intencionalmente mínimo: el foco está en backend, eventos, contenedores y despliegue.
