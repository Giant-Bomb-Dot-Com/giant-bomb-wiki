<?php

namespace MediaWiki\Extension\CookieConsent;

use MediaWiki\MediaWikiServices;
use Wikimedia\Services\ServiceContainer;

/**
 * Getter for all CookieConsent services. This class reduces the risk of mistyping
 * a service name and serves as the interface for retrieving services for CookieConsent.
 *
 * @note Program logic should use dependency injection instead of this class wherever
 * possible.
 *
 * @note This function should only contain static methods.
 */
final class CookieConsentServices {
	/**
	 * Disable the construction of this class by making the constructor private.
	 */
	private function __construct() {
	}

	/**
	 * Get the CookiePreferences service.
	 *
	 * @param ServiceContainer|null $services
	 * @return CookiePreferences
	 */
	public static function getCookiePreferences( ?ServiceContainer $services = null ): CookiePreferences {
		return self::getService( "CookiePreferences", $services );
	}

	/**
	 * Retrieve the specified CookieConsent service.
	 *
	 * @param string $service
	 * @param ServiceContainer|null $services
	 * @return mixed
	 */
	private static function getService( string $service, ?ServiceContainer $services ) {
		return ( $services ?: MediaWikiServices::getInstance() )->getService( "CookieConsent.$service" );
	}
}
