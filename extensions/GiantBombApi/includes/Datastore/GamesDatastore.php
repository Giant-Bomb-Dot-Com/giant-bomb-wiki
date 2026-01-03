<?php

namespace GiantBombApi\Datastore;

/**
 * Datastore connection for retrieving games.
 */
class GamesDatastore extends BaseDatastore {

    /**
     * Get a list of games.
     * @param SortOrder $sortOrder Sort order of the results.
     * @param int $limit How many results to fetch.
     * @param int $offset Result offset.
     * @return array List of filtered, limited & sorted games, along with the total available games.
     */
    public static function getGames( SortOrder $sortOrder, int $limit, int $offset ): array {
        return self::getResources(
            'Games',
            [
                'guid',
                'name',
                'deck',
                'image',
                'release date',
                'platforms',
            ],
            $sortOrder,
            $limit,
            $offset
        );
    }
}
