#!/bin/bash

MAINT_DIR="/var/www/html/maintenance"
NUM_WORKERS=1
MEM_LIMIT="500M"
MAX_JOBS=200

sleep 60
echo "Starting $NUM_WORKERS parallel job queue workers..."

while true; do
  echo "\tbeginning"
  for i in $(seq 1 $NUM_WORKERS); do
      echo "Starting worker #$i..."
      php "$MAINT_DIR/run.php" "$MAINT_DIR/runJobs.php" --memory-limit="$MEM_LIMIT" --maxjobs="$MAX_JOBS" --wait &
      sleep 5
  done
  wait

  echo "\there we go again"
done

echo "All job queue workers have finished."