#!/usr/bin/env sh
set -e

cd /var/www/html/
ls -l | echo

exec apache2-foreground
