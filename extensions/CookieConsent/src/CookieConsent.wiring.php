<?php

/**
 * This file is loaded by MediaWiki\MediaWikiServices::getInstance() during the
 * bootstrapping of the dependency injection framework.
 *
 * @file
 */

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

use MediaWiki\Extension\CookieConsent\CookiePreferences;

return [
	"CookieConsent.CookiePreferences" => static function (): CookiePreferences {
		return new CookiePreferences( RequestContext::getMain() );
	}
];

// @codeCoverageIgnoreEnd
