<?php

namespace MediaWiki\Extension\GiantBombApi\Handlers;

use MediaWiki\Rest\SimpleHandler;

/**
 * Handles requests to the GET /games endpoint.
 */
class GamesHandler extends SimpleHandler {
    public function run() {
        return [ 'success' => 1 ];
    }
}
