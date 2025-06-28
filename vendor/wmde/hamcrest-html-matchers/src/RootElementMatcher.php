<?php

namespace WMDE\HamcrestHtml;

use DOMDocument;
use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\TypeSafeDiagnosingMatcher;

class RootElementMatcher extends TypeSafeDiagnosingMatcher {

	/**
	 * @var Matcher|null
	 */
	private $tagMatcher;

	/**
	 * @param Matcher|null $tagMatcher
	 *
	 * @return static
	 */
	public static function havingRootElement( ?Matcher $tagMatcher = null ) {
		return new static( $tagMatcher );
	}

	public function __construct( ?Matcher $tagMatcher = null ) {
		parent::__construct( self::TYPE_OBJECT, DOMDocument::class );
		$this->tagMatcher = $tagMatcher;
	}

	public function describeTo( Description $description ) {
		$description->appendText( 'having root element ' );
		if ( $this->tagMatcher ) {
			$description->appendDescriptionOf( $this->tagMatcher );
		}
	}

	/**
	 * @param DOMDocument $item
	 * @param Description $mismatchDescription
	 *
	 * @return bool
	 */
	protected function matchesSafelyWithDiagnosticDescription(
		$item, Description $mismatchDescription
	) {
		$DOMNodeList = $item->documentElement->childNodes->item( 0 )->childNodes;
		if ( $DOMNodeList->length > 1 ) {
			// TODO Test this description
			$mismatchDescription->appendText( 'having ' . $DOMNodeList->length . ' root elements ' );
			return false;
		}

		$target = $DOMNodeList->item( 0 );
		if ( !$target ) {
			// TODO Reproduce?
			$mismatchDescription->appendText( 'having no root elements ' );
			return false;
		}

		if ( $this->tagMatcher ) {
			$mismatchDescription->appendText( 'root element ' );
			$this->tagMatcher->describeMismatch( $target, $mismatchDescription );
			return $this->tagMatcher->matches( $target );
		}

		return true;
	}

}
