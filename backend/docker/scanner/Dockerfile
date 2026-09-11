FROM composer:2 AS dependencies

WORKDIR /build

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader


FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        ca-certificates \
        curl-dev \
        openssl \
    && docker-php-ext-install curl \
    && addgroup -S -g 10001 scanner \
    && adduser -S -D -H -u 10001 -G scanner scanner

WORKDIR /scanner

COPY --from=dependencies /build/vendor ./vendor
COPY scanner-runtime.php ./scanner-runtime.php
COPY src ./src

# Runtime application tree is immutable to scanner UID 10001.
# Writable scratch space is supplied only by the bounded /tmp tmpfs
# configured by the parent container runtime.
RUN chown -R root:root /scanner \
    && find /scanner -type d -exec chmod 0555 {} \; \
    && find /scanner -type f -exec chmod 0444 {} \;

USER 10001:10001

ENTRYPOINT ["php", "/scanner/scanner-runtime.php"]
