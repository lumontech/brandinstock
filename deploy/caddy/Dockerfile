# syntax=docker/dockerfile:1

# --- Build del frontend Vue ---
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# --- Caddy: HTTPS automatico (Let's Encrypt), file statici e proxy FastCGI verso Laravel ---
FROM caddy:2-alpine
COPY deploy/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=frontend /app/dist /srv/frontend
