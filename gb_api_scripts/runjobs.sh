#!/bin/bash

MAINT_DIR="/var/www/html/maintenance"
NUM_WORKERS=4
MEM_LIMIT="2048M"

echo "Starting $NUM_WORKERS parallel job queue workers..."

for i in $(seq 1 $NUM_WORKERS); do
    echo "Starting worker #$i..."
    php "$MAINT_DIR/run.php" "$MAINT_DIR/runJobs.php" --memory-limit="$MEM_LIMIT" --wait &
done

wait

echo "All job queue workers have finished."