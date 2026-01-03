<?php

namespace GiantBombApi\Datastore;

/**
 * Datastore connection for retrieving games.
 */
class GamesDatastore extends BaseDatastore {

    /**
     * Get a list of games.
     * @param string $searchQuery Text to search for in the game name.
     * @param SortOrder $sortOrder Sort order of the results.
     * @param int $limit How many results to fetch.
     * @param int $offset Result offset.
     * @return array List of filtered, limited & sorted games, along with the total available games.
     */
    public static function getGames( string $searchQuery, SortOrder $sortOrder, int $limit, int $offset ): array {
        $properties = [
            'guid',
            'name',
            'deck',
            'image',
            'release date',
            'platforms',
        ];
        $filters = [];
        if (!empty($searchQuery)) {
            $filters['name'] = $searchQuery;
        }

        return self::getResources(
            'Games',
            $properties,
            $filters,
            $sortOrder,
            $limit,
            $offset
        );
    }
}
