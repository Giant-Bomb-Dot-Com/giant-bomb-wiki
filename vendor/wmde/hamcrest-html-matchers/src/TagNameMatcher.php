<?php

namespace WMDE\HamcrestHtml;

use DOMElement;
use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\Util;

class TagNameMatcher extends TagMatcher {

	/**
	 * @var Matcher
	 */
	private $tagNameMatcher;

	/**
	 * @param Matcher|string $tagName
	 *
	 * @return self
	 */
	public static function withTagName( $tagName ) {
		return new static( Util::wrapValueWithIsEqual( $tagName ) );
	}

	public function __construct( Matcher $tagNameMatcher ) {
		parent::__construct();
		$this->tagNameMatcher = $tagNameMatcher;
	}

	public function describeTo( Description $description ) {
		$description->appendText( 'with tag name ' )
			->appendDescriptionOf( $this->tagNameMatcher );
	}

	/**
	 * @param DOMElement $item
	 * @param Description $mismatchDescription
	 *
	 * @return bool
	 */
	protected function matchesSafelyWithDiagnosticDescription(
		$item, Description $mismatchDescription
	) {
		if ( $this->tagNameMatcher->matches( $item->tagName ) ) {
			return true;
		}

		$mismatchDescription->appendText( 'tag name ' );
		$this->tagNameMatcher->describeMismatch( $item->tagName, $mismatchDescription );
		return false;
	}

}
