# Cloud database acceptance is recorded separately; hosted image checks remain required.
FROM node:24.22.2-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.5-bookworm@sha256:8104eb9c834d905e5678a183d053ef1a0c650c5f4874f366dfec7eb025e92e06 AS php-base
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions pdo_mysql pdo_pgsql gd intl zip mbstring dom xml xmlreader opcache \
    && php -r 'if (version_compare(PHP_VERSION, "8.5.12", "<")) { exit(1); }'
COPY deploy/php-cloud.ini /usr/local/etc/php/conf.d/99-nfcheck.ini
WORKDIR /app

FROM php-base AS dependencies
COPY --from=composer:2.11.1 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./artisan
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev

FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false PORT=8080 XDG_CONFIG_HOME=/tmp/caddy-config XDG_DATA_HOME=/tmp/caddy-data
COPY --from=dependencies /app /app
COPY public ./public
COPY resources ./resources
COPY --from=frontend /app/public/build ./public/build
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/start-cloud.sh /usr/local/bin/nfcheck-start
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/cache/locks storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u=rwX,g=,o= storage bootstrap/cache \
    && chmod 755 /usr/local/bin/nfcheck-start
USER www-data
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/nfcheck-start"]
