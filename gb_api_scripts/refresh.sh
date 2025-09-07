#!/bin/bash

num_pages=$(php /var/www/html/maintenance/showSiteStats.php | grep "Total pages" | sed 's/[^0-9]*//g')
end_id=0
delta=2000

echo "Beginning refreshLinks.php script"
echo "  Total pages = $num_pages"
echo "  Doing it in $delta-page chunks to avoid memory leak"

while [ "$end_id" -lt "$num_pages" ]; do
start_id=$(($end_id + 1))
end_id=$(($end_id + $delta))
echo "Running refreshLinks.php from $start_id to $end_id"
php /var/www/html/maintenance/run.php refreshLinks --e "$end_id" -- "$start_id"
done

# Just in case there are more IDs beyond the guess we made with showSiteStats, run one more unbounded refreshLinks.php starting at the last ID previously done
start_id=$(($end_id + 1))
echo "Running final refreshLinks.php in case there are more pages beyond $num_pages"
php /var/www/html/maintenance/run.php refreshLinks "$start_id"