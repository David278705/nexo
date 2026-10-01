# syntax=docker/dockerfile:1
#
# La imagen de producción. Una sola imagen para los tres procesos —web, cola y
# tareas programadas—: cada servicio de Railway la arranca con un NEXO_ROL
# distinto. La guía está en docs/08-despliegue.md.

# ---------------------------------------------------------------------------
# 1. PHP con las extensiones y las dependencias de Composer
# ---------------------------------------------------------------------------
#
# FrankenPHP en modo clásico: un solo binario que sirve HTTP y ejecuta PHP, sin
# nginx ni php-fpm ni un supervisor que los coordine.
#
# Clásico y NO en modo worker, a propósito: el modo worker mantiene la
# aplicación viva entre peticiones, y aquí hay estado por petición —la empresa
# en curso de ContextoTenant— que podría pasar de una petición a la siguiente.
# Entre empresas distintas, eso es exactamente la fuga que prohíbe la regla 1.
FROM dunglas/frankenphp:1-php8.4-bookworm AS php

# pdo_pgsql: la base. intl: formatos de número y fecha. zip y gd: Laravel
# Excel. bcmath: aritmética exacta. pcntl: que la cola termine el trabajo en
# curso cuando Railway la detiene. opcache: el código compilado en memoria.
RUN install-php-extensions pdo_pgsql intl zip gd bcmath pcntl opcache

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-nexo.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Las dependencias van antes que el código: mientras composer.lock no cambie,
# Docker reutiliza esta capa y el despliegue no las vuelve a descargar.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache

# ---------------------------------------------------------------------------
# 2. Los assets del frontend
# ---------------------------------------------------------------------------
#
# Con vendor/ a la vista: resources/css/app.css le pide a Tailwind que lea unas
# vistas que viven ahí, y sin ellas el CSS de producción saldría distinto del
# que se probó en local, sin que nada avisara.
FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=php /app/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# 3. La imagen final
# ---------------------------------------------------------------------------
FROM php

COPY --from=assets /app/public/build ./public/build
COPY docker/arrancar.sh /usr/local/bin/arrancar
RUN chmod +x /usr/local/bin/arrancar

# Corre como root, igual que la imagen base de FrankenPHP. Railway monta los
# volúmenes como root, y el de storage/app (los certificados) dejaría de poder
# escribirse con un usuario sin privilegios sin un paso más para cambiar de
# dueño al arrancar. Se prefiere lo simple y conocido; ver docs/08-despliegue.md.

ENV PORT=8080
EXPOSE 8080

CMD ["arrancar"]
