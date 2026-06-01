#!/bin/bash
###############################################################################
# This runs a flaky test (the one is normally succeeds, but sometimes fails
# for whatever reason) several times (until it actually fails).
# Usage:
#	troubleshoot_flaky_test.sh "name of test".
###############################################################################

failed=0

for i in {1..25}; do
	echo "Flaky test detection: RUN #$i"
	tests/phpunit/phpunit.php extensions/Moderation/tests/phpunit/$2 --use-normal-tables --stop-on-failure --filter "$1"

	if [ $? -ne 0 ]; then
		failed=1
		echo "Test failed on RUN #$i." >&2
		break
	fi
done

exit $failed
