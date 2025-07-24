FROM mediawiki:1.43.1

WORKDIR /var/www/html
USER root

# INSTALL SEMANTIC MEDIAWIKI
RUN set -x; \
    apt-get update \
 && apt-get upgrade -y \
 && apt-get install libzip-dev unzip wget -y

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY ./gb_api_scripts /var/www/html/maintenance/gb_api_scripts/

RUN chown -R www-data:www-data /var/www/html

RUN cd /var/www/html \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-extra-special-properties \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-result-formats \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-scribunto dev-master \
 && php /usr/local/bin/composer require --no-update "wikimedia/css-sanitizer:^5.5.0" \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && cd /var/www/html/extensions/ \
 && git clone https://github.com/TopRealm/mediawiki-extensions-AddImgTag AddImgTag \ 
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/TemplateStyles \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/Popups \
 && wget https://github.com/octfx/mediawiki-extensions-TemplateStylesExtender/archive/refs/tags/v2.0.0.zip \
 && unzip v2.0.0.zip && rm v2.0.0.zip && mv mediawiki-extensions-TemplateStylesExtender-2.0.0 TemplateStylesExtender \
 && cd /var/www/html/ \
 && composer update --no-dev

 RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
        sed -i -e "s/^ *memory_limit.*/memory_limit = 4G/g" /usr/local/etc/php/php.ini

# START CONTAINER
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]
