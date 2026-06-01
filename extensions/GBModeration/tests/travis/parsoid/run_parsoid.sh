#!/bin/bash
###############################################################################
# Download and start Parsoid.
# Usage: ./run_parsoid REL1_31
# (where REL1_31 is the branch of MediaWiki)
###############################################################################

branch=$1
GITCLONE_OPTS="--recurse-submodules -j 5"

if [ ! -f parsoid/COMPLETE ]; then
	rm -rf parsoid
	git clone $GITCLONE_OPTS https://gerrit.wikimedia.org/r/p/mediawiki/services/parsoid/deploy parsoid

	# Older MediaWiki doesn't support latest Parsoid.
	# Revert Parsoid to the supported version.
	if [ "$branch" = "REL1_31" ]; then
		PARSOID_DEPLOY_REVISION=1cc68445c46759d0149bc831f0330d2229885d87
	fi

	if [ "x$PARSOID_DEPLOY_REVISION" != "x" ]; then
		( cd parsoid && git checkout --recurse-submodules $PARSOID_DEPLOY_REVISION )
	fi

	touch parsoid/COMPLETE
fi

cp $(dirname $0)/config.yaml parsoid/
cd parsoid && ( PORT=8142 npm start >$TRAVIS_BUILD_DIR/parsoid.log & ) && cd -
