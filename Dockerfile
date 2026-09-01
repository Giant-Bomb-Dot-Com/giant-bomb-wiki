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

# INSTALL DB EXTENSIONS AND REDIS
RUN docker-php-ext-install pdo pdo_mysql
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# LOAD API SCRIPTS - UNNEEDED AFTER DATA TRANSFERRED
COPY ./gb_api_scripts /var/www/html/maintenance/gb_api_scripts/
RUN if [ "$INSTALL_API" = "false" ]; then \
    rm -rf /var/www/html/maintenance/gb_api_scripts/; \
    fi

RUN chown -R www-data:www-data /var/www/html

# guzzle 7.9.2 (pinned exactly by MediaWiki 1.43) now has published security
# advisories that Composer 2.10+ blocks at dependency-resolution time, which
# breaks `composer update` below. Disable advisory blocking so the pinned
# versions still install. Dev/CI image only.
RUN composer config --global policy.advisories.block false

# local patches applied to cloned extensions below
COPY ./docker/patches /tmp/patches

# INSTALL SEMANTIC MEDIAWIKI
# Due to an issue with phpunit 9.6.19, we have to force it to update:
# See https://issues.apache.org/jira/browse/IGNITE-27681
RUN cd /var/www/html \
 && sed -i -e "s/9.6.19/\^9.6.19/" composer.json \
 && sed -i -e "s/5.4.45/\^5.4.45/" composer.json \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update mediawiki/semantic-media-wiki \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-extra-special-properties \
 && php /usr/local/bin/composer require --no-update mediawiki/semantic-result-formats \
 && php /usr/local/bin/composer require --no-update "mediawiki/semantic-scribunto:^2.3" \
 && php /usr/local/bin/composer require --no-update "wikimedia/css-sanitizer:^5.5.0" \
 && COMPOSER=composer.local.json php /usr/local/bin/composer require --no-update edwardspec/mediawiki-aws-s3:0.14.0 \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 && cd /var/www/html/extensions/ \
 # PageForms + VEForAll run master (REL branches lag; maintainers advise master) -> pin known-good commits
 && git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/PageForms.git \
 && git -C PageForms checkout ff33a2101a1a3588de29aa52c147e3e1d36672c0 \
 # tokens fields with 3+ values get misread as dates -> php8 warnings (drop when upstreamed)
 && git -C PageForms apply /tmp/patches/pageforms-tokens-not-a-date.patch \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/DisplayTitle \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/TemplateStyles \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/Popups \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/UrlGetParameters \
 && git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/VEForAll \
 && git -C VEForAll checkout 636a718f0cbd70e25d5d2700d78dad204e1be5ee \
 && git clone -b 'REL1_43' --single-branch --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/ExternalData \
 && wget https://github.com/octfx/mediawiki-extensions-TemplateStylesExtender/archive/refs/tags/v2.0.0.zip \
 && unzip v2.0.0.zip && rm v2.0.0.zip && mv mediawiki-extensions-TemplateStylesExtender-2.0.0 TemplateStylesExtender \
 # GTag has no release tags -> pin to a known-good master commit (requires MW 1.43+)
 && git clone https://github.com/SkizNet/mediawiki-GTag.git GTag \
 && git -C GTag checkout 59c5504da491b3e2d7f7d38c520353332611405d \
 && cd /var/www/html/ \
 && composer update --no-dev

# strip ACL params AFTER composer update — composer reinstalls extensions/AWS
# last (installer-name AWS) and used to resurrect them; GCS uniform
# bucket-level access rejects any ACL, breaking uploads. grep guard fails the
# build if they ever come back.
RUN sed -i "/'ACL'/d" /var/www/html/extensions/AWS/s3/AmazonS3FileBackend.php \
 && ! grep -q "'ACL'" /var/www/html/extensions/AWS/s3/AmazonS3FileBackend.php

# smw 7.0 pins its query-cache stats to CACHE_DB -> every request rewrites one
# hot objectcache row and convoys the db. route them to the main cache.
RUN sed -i "s/getObjectCache( CACHE_DB )/getObjectCache( CACHE_ANYTHING )/" \
      /var/www/html/extensions/SemanticMediaWiki/src/Services/ServicesFactory.php \
 && ! grep -q "getObjectCache( CACHE_DB )" /var/www/html/extensions/SemanticMediaWiki/src/Services/ServicesFactory.php

# GCS uses uniform bucket-level ACL, so re-uploads need ignorewarnings
RUN sed -i 's/ignorewarnings: false/ignorewarnings: true/' /var/www/html/resources/src/mediawiki.Upload.js

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
    echo "apc.shm_size=256M" >> /usr/local/etc/php/php.ini && \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/php.ini && \
    echo "session.gc_maxlifetime=86400" >> /usr/local/etc/php/php.ini && \
    sed -i -e "s/^ *memory_limit.*/memory_limit = 4G/g" /usr/local/etc/php/php.ini && \
    echo "LimitRequestFieldSize 16384\nLimitRequestLine 16384" \
      > /etc/apache2/conf-enabled/request-limits.conf

# Directory for logging
RUN mkdir -p -m 740 /var/log/mediawiki && \
    chown -R www-data:www-data /var/log/mediawiki

# Custom extensions packaged with the image
COPY --chown=www-data:www-data ./extensions/GiantBombResolve /var/www/html/extensions/GiantBombResolve
COPY --chown=www-data:www-data ./extensions/GiantBombMetaTags /var/www/html/extensions/GiantBombMetaTags
COPY --chown=www-data:www-data ./extensions/GbSessionProvider/ /var/www/html/extensions/GbSessionProvider
COPY --chown=www-data:www-data ./extensions/GBModeration /var/www/html/extensions/GBModeration
COPY --chown=www-data:www-data ./extensions/GBEnvLuaBridge /var/www/html/extensions/GBEnvLuaBridge
COPY --chown=www-data:www-data ./extensions/GBRelated /var/www/html/extensions/GBRelated
COPY --chown=www-data.www-data ./extensions/GBVirtualReviewPages /var/www/html/extensions/GBVirtualReviewPages
COPY --chown=www-data:www-data ./extensions/GBCloudflarePurge /var/www/html/extensions/GBCloudflarePurge
COPY --chown=www-data:www-data ./extensions/GBCatalogSync /var/www/html/extensions/GBCatalogSync
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
