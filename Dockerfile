# DataDachs – Dockerfile für ARM64 (Raspberry Pi 5)
FROM php:8.3-fpm-alpine

# System-Abhängigkeiten
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    libzip \
    unzip \
    && docker-php-ext-install pdo_sqlite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App-Verzeichnis
WORKDIR /var/www/datadachs

# Nginx-Konfiguration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-pool.conf /usr/local/etc/php-fpm.d/www.conf

# App-Code (zuerst nur composer.json für Layer-Caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Restlicher Code
COPY . .

# Berechtigungen: nicht-Root-Betrieb
RUN addgroup -g 1000 datadachs \
    && adduser -u 1000 -G datadachs -s /bin/sh -D datadachs \
    && chown -R datadachs:datadachs /var/www/datadachs \
    && chown -R datadachs:datadachs /var/lib/nginx \
    && chown -R datadachs:datadachs /var/log/nginx \
    && chown -R datadachs:datadachs /run \
    && mkdir -p /var/www/datadachs/storage/{uploads,jobs,tmp} \
    && chown -R datadachs:datadachs /var/www/datadachs/storage

# Temporäre Verzeichnisse beschreibbar, Rest read-only vorbereitet
VOLUME ["/var/www/datadachs/storage"]

USER datadachs

EXPOSE 8080

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8080/health || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
