<?php

namespace MediaWiki\Extension\GiantBombApi\Handlers;

use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Rest\SimpleHandler;

// TODO: move this to a constants file
const DEFAULT_PAGE_SIZE = 48;

/**
 * Handles requests to the GET /games endpoint.
 */
class GamesHandler extends SimpleHandler {

    private const VALID_SORT_VALUES = [
        '',
        'release-date-asc',
        'release-date-desc',
        'title-asc',
        'title-desc',
    ];

    public function getParamSettings() {
        return [
            'search' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'platform' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'sort' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => self::VALID_SORT_VALUES,
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'page' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'perPage' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }

    public function needsWriteAccess() {
        return false;
    }

    /**
     * Run the handler, fetching a list of games.
     */
    public function run() {
        $queryParams = $this->getValidatedParams();
        $searchQuery = trim($queryParams['search'] ?? '');
        $platformFilter = trim($queryParams['platform'] ?? '');
        $sortOrder = trim($queryParams['sort'] ?? '');
        $currentPage = max(1, $queryParams['page'] ?? 1);
        $itemsPerPage = max(1, min(100, $queryParams['perPage'] ?? DEFAULT_PAGE_SIZE));

        print_r($queryParams);
        die();

        return [ 'success' => 1 ];
    }
}
