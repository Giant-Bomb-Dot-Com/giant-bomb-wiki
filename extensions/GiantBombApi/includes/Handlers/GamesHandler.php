<?php

namespace GiantBombApi\Handlers;

use GiantBombApi\Datastore\GamesDatastore;
use GiantBombApi\Datastore\SortOrder;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

// TODO: move this to a constants file
const DEFAULT_LIMIT = 100;

/**
 * Handles requests to the GET /games endpoint.
 */
class GamesHandler extends SimpleHandler {

    public function getParamSettings(): array {
        return [
            'limit' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'offset' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'sort' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => SortOrder::values(),
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }

    public function needsWriteAccess(): bool {
        return false;
    }

    public function run(): array {
        $queryParams = $this->getValidatedParams();
        $limit = $queryParams['limit'] ?? DEFAULT_LIMIT;
        $offset = $queryParams['offset'] ?? 0;
        $sort = SortOrder::from($queryParams['sort'] ?? '');

        list($results, $totalCount) = GamesDatastore::getGames($sort, $limit, $offset);

        return [
            'results' => $results,
            'total_results' => $totalCount,
        ];
    }
}
