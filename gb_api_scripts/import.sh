#!/bin/bash

XML_DIR="./import_xml"

if [ ! -d "$XML_DIR" ]; then
    echo "Error: Directory '$XML_DIR' not found."
    exit 1
fi

for xml_file in "$XML_DIR"/*.xml; do
    if [ -f "$xml_file" ]; then
        echo "Importing $xml_file..."
        php /var/www/html/maintenance/run.php /var/www/html/maintenance/importDump.php < "$xml_file"
    fi
done

php /var/www/html/maintenance/run.php /var/www/html/maintenance/rebuildrecentchanges.php
php /var/www/html/maintenance/run.php /var/www/html/maintenance/initSiteStats.php
