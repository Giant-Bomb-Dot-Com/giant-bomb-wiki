cd /var/www/html/extensions/SemanticMediaWiki/maintenance
SKIPLVL=1
for i in $(seq 1 460); 
do
    php rebuildData.php -s $SKIPLVL -e $(($SKIPLVL+1999))
    SKIPLVL=$(($SKIPLVL + 2000))
done