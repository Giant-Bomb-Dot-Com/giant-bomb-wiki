#!/bin/bash

MAX_PAGE_ID=$(php /var/www/html/maintenance/showSiteStats.php | grep "Total pages" | sed 's/[^0-9]*//g')
# The size of each batch
BATCH_SIZE=1000

# --- Execution ---
START_ID=1
END_ID=$BATCH_SIZE

# Initial run with --force
echo "Starting initial batch (deleting old data)..."
php /var/www/html/maintenance/rebuildData.php -vfd -s $START_ID -e $END_ID
if [ $? -ne 0 ]; then
    echo "Initial batch failed. Exiting."
    exit 1
fi

# Subsequent runs
while [ $END_ID -lt $num_pages ]
do
    START_ID=$((END_ID + 1))
    END_ID=$((END_ID + BATCH_SIZE))

    echo "Processing batch from page ID $START_ID to $END_ID..."
    php /var/www/html/maintenance/rebuildData.php -v -s $START_ID -e $END_ID
    if [ $? -ne 0 ]; then
        echo "Batch failed for range $START_ID-$END_ID. Exiting."
        exit 1
    fi
done

echo "Rebuild process complete."
