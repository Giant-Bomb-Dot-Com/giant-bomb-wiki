FROM mediawiki:1.43.6

# This Dockerfile is for development and CI environments where configuration
# files are mounted as volumes. For production deployments, use Dockerfile.prod
# which includes LocalSettings.php and the GiantBomb skin in the image.

ARG INSTALL_GCSFUSE="false"
ARG INSTALL_API="false"

WORKDIR /var/www/html
USER root

# INSTALL DB EXTENSIONS
RUN docker-php-ext-install pdo pdo_mysql

# INSTALL SEMANTIC MEDIAWIKI
RUN set -x; \
    apt-get update \
 && apt-get upgrade -y \
 && apt-get install gnupg lsb-release libzip-dev unzip wget -y

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# LOAD API SCRIPTS - UNNEEDED AFTER DATA TRANSFERRED
COPY ./gb_api_scripts /var/www/html/maintenance/gb_api_scripts/
RUN if [ "$INSTALL_API" = "false" ]; then \
    rm -rf /var/www/html/maintenance/gb_api_scripts/; \
    fi

RUN chown -R www-data:www-data /var/www/html

# MOUNT GCS IMAGE FOLDER
RUN if [ "$INSTALL_GCSFUSE" = "true" ]; then \
    lsb_release -c -s > /tmp/lsb_release && \
    GCSFUSE_REPO=$(cat /tmp/lsb_release) && \
    wget -qO - https://packages.cloud.google.com/apt/doc/apt-key.gpg | gpg --dearmor | tee /usr/share/keyrings/cloud.google.gpg >/dev/null && \
    echo "deb [signed-by=/usr/share/keyrings/cloud.google.gpg] https://packages.cloud.google.com/apt gcsfuse-$GCSFUSE_REPO main" | tee /etc/apt/sources.list.d/gcsfuse.list && \
    apt-get update && apt-get install -y gcsfuse; \
    fi

RUN cd /var/www/html \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-extra-special-properties \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-result-formats \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-scribunto dev-master \
 && php /usr/local/bin/composer require --no-update "wikimedia/css-sanitizer:^5.5.0" \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && cd /var/www/html/extensions/ \
 && git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/PageForms.git \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/DisplayTitle \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/TemplateStyles \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/Popups \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/UrlGetParameters \
 && wget https://github.com/octfx/mediawiki-extensions-TemplateStylesExtender/archive/refs/tags/v2.0.0.zip \
 && unzip v2.0.0.zip && rm v2.0.0.zip && mv mediawiki-extensions-TemplateStylesExtender-2.0.0 TemplateStylesExtender \
 && cd /var/www/html/ \
 && php /usr/local/bin/composer config audit.ignore '["PKSA-z3gr-8qht-p93v"]' --json \ 
 && composer update --no-dev

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
    echo "apc.shm_size=256M" >> /usr/local/etc/php/php.ini && \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/php.ini && \
    sed -i -e "s/^ *memory_limit.*/memory_limit = 4G/g" /usr/local/etc/php/php.ini

# Directory for logging
RUN mkdir -p -m 740 /var/log/mediawiki && \
    chown -R www-data:www-data /var/log/mediawiki

# Custom extensions packaged with the image
COPY --chown=www-data:www-data ./extensions/GiantBombResolve /var/www/html/extensions/GiantBombResolve
COPY --chown=www-data:www-data ./extensions/GiantBombMetaTags /var/www/html/extensions/GiantBombMetaTags

# Installation script for a new wiki (which copies the LocalSettings.php)
COPY --chmod=755 installwiki.sh /installwiki.sh

# Configurations for test suites
COPY --chown=www-data:www-data phpunit.xml.dist /var/www/html/phpunit.xml.dist

# START CONTAINER
# Route /wiki/* to docroot for ResourceLoader and API when served under a path prefix
COPY .htaccess /var/www/html/.htaccess

COPY --chmod=755 scripts/wiki-admin.sh /usr/local/bin/wiki-admin
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]
