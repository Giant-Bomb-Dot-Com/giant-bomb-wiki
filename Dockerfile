FROM mediawiki:1.43.1

WORKDIR /var/www/html
USER root

RUN --mount=target=/var/www/html/extensions,type=cache

RUN set -x; \
    apt-get update \
 && apt-get upgrade -y \
 && apt-get install libzip-dev -y

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cd /var/www/html \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && composer update --no-dev
 
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/bin/sh",/entrypoint.sh"]
