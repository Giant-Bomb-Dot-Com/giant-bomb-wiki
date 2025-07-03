FROM mediawiki:1.43.1

WORKDIR /var/www/html
USER root

# INSTALL SEMANTIC MEDIAWIKI
RUN set -x; \
    apt-get update \
 && apt-get upgrade -y \
 && apt-get install libzip-dev unzip wget -y

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cd /var/www/html \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-extra-special-properties \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-result-formats \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-scribunto dev-master \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && cd /var/www/html/extensions/ \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/TemplateStyles \
 && wget https://github.com/octfx/mediawiki-extensions-TemplateStylesExtender/archive/refs/tags/v2.0.0.zip \
 && unzip v2.0.0.zip && rm v2.0.0.zip && mv mediawiki-extensions-TemplateStylesExtender-2.0.0 TemplateStylesExtender \
 && cd /var/www/html/ \
 && composer update --no-dev
 
# START CONTAINER
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/bin/sh",/entrypoint.sh"]
