<?php

namespace GiantBombApi\Datastore;

/**
 * Datastore connection for retrieving games.
 */
class GamesDatastore {

    /**
     * Get a list of games.
     * @param SortOrder $sort Sort order of the results.
     * @param int $limit How many results to fetch.
     * @param int $offset Result offset.
     * @return array List of games based off the search/filter parameters.
     */
    public static function getGames( SortOrder $sort, int $limit, int $offset ): array {
        return [1,2,3];
    }
}
