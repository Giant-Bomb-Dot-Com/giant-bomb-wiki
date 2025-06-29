<?php
use MediaWiki\MediaWikiServices;
/**
 * A fork of Oasis' NavigationService with some changes.
 * Namely the name was changed and "magic word" handling was removed from
 * parseMessage() and some (related) unused functions were also removed.
 */
class GamepressSkinNavigationService {

	/** @var bool Is the message we're supposed to parse in the wiki's content language (true) or not? */
	public $forContent;

	const version = '0.01';

	/**
	 * Parses a system message by exploding along newlines.
	 *
	 * @param string $messageName Name of the MediaWiki message to parse
	 * @param int $duration Cache duration for memcached calls
	 * @param array $maxChildrenAtLevel
	 * @param bool $forContent Is the message we're supposed to parse in
	 *								the wiki's content language (true) or not?
	 * @return array
	 */
	public function parseMessage( $messageName, $duration, $maxChildrenAtLevel = [], $forContent = false ) {
		global $wgLang;

		$this->forContent = $forContent;

		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();
		$cache = $services->getMainWANObjectCache();
		$useCache = $wgLang->getCode() == $contLang->getCode();

		if ( $useCache || $this->forContent ) {
			$cacheKey = $cache->makeKey( $messageName, self::version );
			$nodes = $cache->get( $cacheKey );
		}

		if ( empty( $nodes ) ) {
			if ( $this->forContent ) {
				$lines = explode( "\n", wfMessage( $messageName )->inContentLanguage()->text() );
			} else {
				$lines = explode( "\n", wfMessage( $messageName )->text() );
			}
			$nodes = $this->parseLines( $lines, $maxChildrenAtLevel );

			if ( $useCache || $this->forContent ) {
				// Phan *really* has a thing for $cacheKey below, eh
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				$cache->set( $cacheKey, $nodes, $duration );
				// @phan-suppress-previous-line PhanTypeMismatchArgumentNullable
			}
		}

		return $nodes;
	}

	/**
	 * Function used by parseMessage() above.
	 *
	 * @param array $lines Newline-separated lines from the supplied MW: msg
	 * @param array $maxChildrenAtLevel
	 * @return array
	 */
	private function parseLines( $lines, $maxChildrenAtLevel = [] ) {
		$nodes = [];

		if ( is_array( $lines ) && count( $lines ) > 0 ) {
			$lastDepth = 0;
			$i = 0;
			$lastSkip = null;

			foreach ( $lines as $line ) {
				// we are interested only in lines that are not empty and start with asterisk
				if ( trim( $line ) != '' && $line[0] == '*' ) {
					$depth = strrpos( $line, '*' ) + 1;

					if ( $lastSkip !== null && $depth >= $lastSkip ) {
						continue;
					} else {
						$lastSkip = null;
					}

					if ( $depth == $lastDepth + 1 ) {
						$parentIndex = $i;
					} elseif ( $depth == $lastDepth ) {
						// @phan-suppress-next-line PhanTypeInvalidDimOffset
						$parentIndex = $nodes[$i]['parentIndex'];
					} else {
						for ( $x = $i; $x >= 0; $x-- ) {
							if ( $x == 0 ) {
								$parentIndex = 0;
								break;
							}
							// @phan-suppress-next-line PhanTypeInvalidDimOffset
							if ( $nodes[$x]['depth'] <= $depth - 1 ) {
								$parentIndex = $x;
								break;
							}
						}
					}

					if ( isset( $maxChildrenAtLevel[$depth - 1] ) ) {
						// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
						if ( isset( $nodes[$parentIndex]['children'] ) ) {
							// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
							if ( count( $nodes[$parentIndex]['children'] ) >= $maxChildrenAtLevel[$depth - 1] ) {
								$lastSkip = $depth;
								continue;
							}
						}
					}

					$node = $this->parseOneLine( $line );
					// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
					$node['parentIndex'] = $parentIndex;
					$node['depth'] = $depth;

					$nodes[$node['parentIndex']]['children'][] = $i + 1;
					$nodes[$i + 1] = $node;
					$lastDepth = $node['depth'];
					$i++;
				}
			}
		}

		return $nodes;
	}

	/**
	 * @param string $line Line to parse
	 * @return array
	 */
	private function parseOneLine( $line ) {
		// trim spaces and asterisks from line and then split it to maximum two chunks
		$lineArr = explode( '|', trim( $line, '* ' ), 2 );

		// trim [ and ] from line to have just http://en.wikipedia.org instead of [http://en.wikipedia.org] for external links
		$lineArr[0] = trim( $lineArr[0], '[]' );

		if ( count( $lineArr ) == 2 && $lineArr[1] != '' ) {
			$link = trim( wfMessage( $lineArr[0] )->inContentLanguage()->text() );
			$desc = trim( $lineArr[1] );
		} else {
			$link = $desc = trim( $lineArr[0] );
		}

		$text = $this->forContent ? wfMessage( $desc )->inContentLanguage() : wfMessage( $desc );
		if ( $text->isDisabled() ) {
			$text = $desc;
		}

		if ( wfMessage( $lineArr[0] )->isDisabled() ) {
			$link = $lineArr[0];
		}

		if ( preg_match( '/^(?:' . wfUrlProtocols() . ')/', $link ) ) {
			$href = $link;
		} else {
			if ( !$link ) {
				$href = '#';
			} elseif ( $link[0] == '#' ) {
				$href = '#';
			} else {
				$title = Title::newFromText( $link );
				if ( is_object( $title ) ) {
					$href = $title->fixSpecialName()->getLocalURL();
				} else {
					$href = '#';
				}
			}
		}

		return [
			'text' => $text,
			'href' => $href
		];
	}

}