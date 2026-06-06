# AGENTS.md — Frontend (React + Vite)

## Scope

Minimal React 18 SPA for order management. Intentionally simple — the project focus is on backend and infrastructure.

## Tech Stack

| Tool    | Version | Purpose              |
| ------- | ------- | -------------------- |
| React   | 18      | UI library           |
| Vite    | —       | Dev server + bundler |
| JSX     | —       | Templating           |

No TypeScript. No router. No state management library. No component library.

## Current Files

```
frontend/
├── src/
│   └── main.jsx        # Entire app in one file (~80 lines)
├── public/
├── Dockerfile          # Multi-stage: Node build → Nginx serve
└── nginx.conf          # Proxy /api to backend
```

## Architecture

Single `App` component with:
- **State**: `useState` for orders list, form fields (email, items, total)
- **API calls**: `fetch()` to `/api/orders` (GET list, POST create)
- **Real-time**: `EventSource` connected to Mercure hub for live updates
- **Routing**: None — single page

## Real-Time Flow

```
Mercure Hub (SSE)
    ↓ topic: /orders/*/status
EventSource.onmessage
    ↓ parse JSON
setOrders(prev => prev.map(...))  // update matching order status
```

## Docker

Multi-stage build:
1. `node:20-alpine` → `npm run build`
2. `nginx:alpine` → serve static files from `/dist`

## Known Technical Debt

- **Single file**: Everything in `main.jsx` — no component splitting
- **Hardcoded Mercure URL**: `http://localhost:3001` baked in code. Should be an env variable
- **No error handling**: `fetch()` calls have no `.catch()` or error state
- **No loading states**: No spinners or skeleton UI
- **No form validation**: Only HTML `required` attribute
- **No TypeScript**: No type safety
- **No tests**: No testing framework configured
- **Inline styles**: `style={{ maxWidth: 600, ... }}` — no CSS/CSS-in-JS
- **No auth**: No authentication or authorization
- **Items as comma-separated string**: UX is poor — no item management UI
