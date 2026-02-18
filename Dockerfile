FROM mediawiki:1.43.6

# This Dockerfile is for development and CI environments where configuration
# files are mounted as volumes. For production deployments, use Dockerfile.prod
# which includes LocalSettings.php and the GiantBomb skin in the image.

ARG INSTALL_API="false"

WORKDIR /var/www/html
USER root

RUN set -x; \
    apt-get update \
 && apt-get upgrade -y \
 && apt-get install gnupg lsb-release libzip-dev unzip wget -y

# INSTALL DB EXTENSIONS
RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# LOAD API SCRIPTS - UNNEEDED AFTER DATA TRANSFERRED
COPY ./gb_api_scripts /var/www/html/maintenance/gb_api_scripts/
RUN if [ "$INSTALL_API" = "false" ]; then \
    rm -rf /var/www/html/maintenance/gb_api_scripts/; \
    fi

RUN chown -R www-data:www-data /var/www/html

# INSTALL SEMANTIC MEDIAWIKI
# Due to an issue with phpunit 9.6.19, we have to force it to update:
# See https://issues.apache.org/jira/browse/IGNITE-27681
RUN cd /var/www/html \
 && sed -i -e "s/9.6.19/\^9.6.19/" composer.json \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-extra-special-properties \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-result-formats \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-scribunto dev-master \
 && php /usr/local/bin/composer require --no-update "wikimedia/css-sanitizer:^5.5.0" \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update edwardspec/mediawiki-aws-s3:0.14.0 \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && cd /var/www/html/extensions/ \
 && git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/PageForms.git \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/DisplayTitle \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/TemplateStyles \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/Popups \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/UrlGetParameters \
 && git clone -b 'v1.9.3' --single-branch --depth 1 https://github.com/edwardspec/mediawiki-moderation.git Moderation \
 && git clone --depth 1 https://github.com/edwardspec/mediawiki-aws-s3.git AWS \
 && sed -i "/'ACL'/d" AWS/s3/AmazonS3FileBackend.php \
 && wget https://github.com/octfx/mediawiki-extensions-TemplateStylesExtender/archive/refs/tags/v2.0.0.zip \
 && unzip v2.0.0.zip && rm v2.0.0.zip && mv mediawiki-extensions-TemplateStylesExtender-2.0.0 TemplateStylesExtender \
 && cd /var/www/html/ \
 && php /usr/local/bin/composer config audit.ignore '["PKSA-z3gr-8qht-p93v"]' --json \ 
 && composer update --no-dev

# GCS uses uniform bucket-level ACL, so re-uploads need ignorewarnings
RUN sed -i 's/ignorewarnings: false/ignorewarnings: true/' /var/www/html/resources/src/mediawiki.Upload.js

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
    echo "apc.shm_size=256M" >> /usr/local/etc/php/php.ini && \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/php.ini && \
    echo "session.gc_maxlifetime=86400" >> /usr/local/etc/php/php.ini && \
    sed -i -e "s/^ *memory_limit.*/memory_limit = 4G/g" /usr/local/etc/php/php.ini

# Directory for logging
RUN mkdir -p -m 740 /var/log/mediawiki && \
    chown -R www-data:www-data /var/log/mediawiki

# Custom extensions packaged with the image
COPY --chown=www-data:www-data ./extensions/GiantBombResolve /var/www/html/extensions/GiantBombResolve
COPY --chown=www-data:www-data ./extensions/GiantBombMetaTags /var/www/html/extensions/GiantBombMetaTags
COPY --chown=www-data:www-data ./extensions/GbSessionProvider/ /var/www/html/extensions/GbSessionProvider
RUN cd /var/www/html/extensions/GbSessionProvider && composer update --no-dev

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
