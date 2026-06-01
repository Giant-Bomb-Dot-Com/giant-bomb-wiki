<?php

namespace MediaWiki\Skin\GiantBomb\Test;

use MediaWikiIntegrationTestCase;
use ReflectionMethod;
use SkinGiantBomb;

/**
 * @covers \SkinGiantBomb::cleanSlugFallback
 */
class SkinGiantBombTest extends MediaWikiIntegrationTestCase {

	private function invokeCleanSlug( string $pageTitle, string $prefix ): string {
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/SkinGiantBomb.php";
		$method = new ReflectionMethod( SkinGiantBomb::class, 'cleanSlugFallback' );
		$method->setAccessible( true );
		return $method->invoke( null, $pageTitle, $prefix );
	}

	/**
	 * @dataProvider provideCleanSlugCases
	 */
	public function testCleanSlugFallback( string $pageTitle, string $prefix, string $expected, string $why ): void {
		$this->assertSame( $expected, $this->invokeCleanSlug( $pageTitle, $prefix ), $why );
	}

	public function provideCleanSlugCases(): array {
		return [
			'strips namespace prefix'         => [ 'Games/Doom',                        'Games/',      'Doom',             'simple case' ],
			'strips trailing 5-digit id'      => [ 'Games/Sprout 64629',                'Games/',      'Sprout',           'production-observed disambig' ],
			'strips underscore-form id'       => [ 'Games/Sprout_64629',                'Games/',      'Sprout',           'underscore variant' ],
			'multi-word + disambig id'        => [ 'Games/Diamond Quest 65309',         'Games/',      'Diamond Quest',    'screenshot example' ],
			'preserves 1-digit sequel'        => [ 'Games/Tekken 7',                    'Games/',      'Tekken 7',         '<5 digits => not stripped' ],
			'preserves 2-digit year'          => [ 'Games/Madden 99',                   'Games/',      'Madden 99',        '90s sports titles' ],
			'preserves 4-digit retro number'  => [ 'Games/Halo 2600',                   'Games/',      'Halo 2600',        'Atari 2600 demake' ],
			'preserves 4-digit year'          => [ 'Games/Madden NFL 2008',             'Games/',      'Madden NFL 2008',  'sports year suffix' ],
			'works for Characters prefix'     => [ 'Characters/Hero Guy 99999',         'Characters/', 'Hero Guy',         'same logic, different prefix' ],
			'works for Franchises prefix'     => [ 'Franchises/Strike Series 88888',    'Franchises/', 'Strike Series',    'same logic, franchise prefix' ],
			'no-op when prefix absent'        => [ 'Sprout',                            'Games/',      'Sprout',           'tolerant of unprefixed input' ],
			'underscores converted to spaces' => [ 'Games/Final_Fantasy_VII',           'Games/',      'Final Fantasy VII','underscore -> space' ],
		];
	}
}
