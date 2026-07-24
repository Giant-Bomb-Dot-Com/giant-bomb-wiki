if [ -f /data/initial_import_data_xmls.zip ]; then
  echo "-> Importing initial data."
  cd /data && unzip initial_import_data_xmls.zip 
  cd initial_import_data_xmls  
  chmod +x import.sh
  ./import.sh
fi