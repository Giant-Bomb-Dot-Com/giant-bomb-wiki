#!/bin/bash

# --- CONFIGURATION ---
CATEGORY=""
TEMPLATE=""
BATCH=2500
# ---------------------

# Parse flags
# c: means -c requires an argument, t: for -t, and b: for -b
while getopts "c:t:b:" opt; do
  case $opt in
    c) CATEGORY="$OPTARG" ;;
    t) TEMPLATE="$OPTARG" ;;
    b) BATCH="$OPTARG" ;;
    \?) echo "Invalid option: -$OPTARG" >&2; exit 1 ;;
  esac
done

echo "Starting with Category: $CATEGORY, Template: $TEMPLATE, Batch: $BATCH"

# Initialize the file if it doesn't exist
echo "0" > last_id.txt

# The loop: Run until the script finds 0 rows (returns a specific exit code)
while [ $(cat last_id.txt) != "DONE" ]; do
    START_ID=$(cat last_id.txt)
    echo "--- Starting new process at ID: $START_ID ---"

    # Run your script for ONE batch
    php -d memory_limit=1G run.php parseImageDivToTag.php \
        --category="$CATEGORY" \
        --template="$TEMPLATE" \
        --batch=$BATCH \
        --start-id=$START_ID

    # Check if we should stop (you can add a check in PHP to write "DONE" to the file)
done