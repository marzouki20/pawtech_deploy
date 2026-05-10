FROM dunglas/frankenphp:1-php8.2-bookworm

WORKDIR /app

RUN install-php-extensions \
    pdo_mysql \
    intl \
    mbstring \
    xml \
    curl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . .

ARG APP_ENV=prod
ARG APP_DEBUG=0
ARG APP_SECRET=build-placeholder

RUN APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG APP_SECRET=$APP_SECRET \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]