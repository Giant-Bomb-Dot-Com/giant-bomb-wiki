#!/usr/bin/env sh
set -e

cd /var/www/html/
ls -l | echo


# If LocalSettings.php exists in the host-mounted config folder, copy it in
if [ ! -f /var/.installed ]; then
  #make sure our db has time to start up
  echo "LocalSettings.php not found. Starting install..."  
  cd /var/www/html/
  #INSTALL MEDIAWIKI
  php maintenance/install.php \
 --dbtype=mysql \
 --dbname=$MW_DB_NAME \
 --dbserver=$MW_DB_HOST \
 --installdbuser=root \
 --installdbpass=$MARIADB_ROOT_PASSWORD \
 --dbuser=$MW_DB_USER \
 --dbpass=$MW_DB_PASSWORD \
 --server=$MW_SITE_SERVER \
 --lang=en \
 --pass=$MW_PASS \
 "$MW_SITE_NAME" "$MW_DB_USER"
 
 cd /var/www/html/ && php maintenance/update.php
 
 cp /config/LocalSettings.php /var/www/html/LocalSettings.php

 /usr/sbin/apache2ctl start
 
 echo "Starting wiki to patch system and db..."

 RESP=$(curl  --connect-timeout 5 \
    --max-time 10 \
    --retry 5 \
    --retry-delay 0 \
    --retry-max-time 40 \
    -s -L 'http://localhost/index.php/Main_Page')

 if [ -z "`$RESP | grep "ERROR_SCHEMA_INVALID_KEY"`"  ]; then
  echo "PATCHING SCHEMA"
  cd /var/www/html/ && php maintenance/run.php update
 fi

 if [ -f /data/wiki_init.zip ]; then
   echo "Importing initial templates and pages."
   cd /data && unzip wiki_init.zip
   cd /var/www/html/maintenance
   php ./importDump.php < /data/wiki_init.xml
   rm /data/wiki_init.xml
 fi

 /usr/sbin/apache2ctl stop
 touch /var/.installed
fi

# Place this after the above patch so the process doesn't
# get shut down prematurely
if [ "$MV_ENV" = "prod" ]; then
  WWW_DATA_UID=$(id -u www-data)
  WWW_DATA_GID=$(id -g www-data)

  gcsfuse --foreground \
          -o allow_other \
          --dir-mode=755 \
          --file-mode=644 \
          --uid="$WWW_DATA_UID" \
          --gid="$WWW_DATA_GID" \
          "$GCS_BUCKET_NAME" \
          /var/www/html/images &
  sleep 5
  if ! mountpoint -q /var/www/html/images; then
      echo "GCSFuse mount failed!"
      exit 1
  fi
fi

# Disable script execution in images folder
chmod 644 /var/www/html/images/.htaccess
if [ -f /var/www/html/images/.htaccess ]; then
    cat <<'EOF' >> /var/www/html/images/.htaccess
# Prevent the execution of common script types.
<FilesMatch "\.(php|phtml|php3|php4|php5|php6|inc|js|pl|py|cgi|sh|bash|zsh)$">
    Require all denied
</FilesMatch>
EOF
else
    cat <<'EOF' > /var/www/html/images/.htaccess
# Set the X-Content-Type-Options header to prevent MIME sniffing.
<IfModule headers_module>
    Header set X-Content-Type-Options "nosniff"
</IfModule>

# Prevent the execution of common script types.
<FilesMatch "\.(php|phtml|php3|php4|php5|php6|inc|js|pl|py|cgi|sh|bash|zsh)$">
    Require all denied
</FilesMatch>

# Explicitly turn off the PHP engine in this directory as a redundant layer of security.
<IfModule php7_module>
    php_flag engine off
</IfModule>
<IfModule php_module>
    php_flag engine off
</IfModule>
EOF
fi
chmod 444 /var/www/html/images/.htaccess

cp /config/LocalSettings.php /var/www/html/LocalSettings.php
exec apache2-foreground
