CORES=$(nproc --all)
SKIPAMT=$((426000 / $CORES))
SKIPLVL=0

if [ -f /data/fulldatadump.gz ]; then
  echo "-> Importing full data backup."
  cd /data && gunzip -k fulldatadump.gz 
  mv fulldatadump fulldatadump.xml
  cd /var/www/html/maintenance
  for i in $(seq 1 $CORES); 
  do
      php importDump.php --namespaces=0 --skip-to=$SKIPLVL < /data/fulldatadump.xml &
      SKIPLVL=$(($SKIPLVL + $SKIPAMT))
  done  
fi