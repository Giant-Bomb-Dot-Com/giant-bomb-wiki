<?php

namespace WMDE\HamcrestHtml;

use DOMDocument;
use DOMNode;
use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\TypeSafeDiagnosingMatcher;

class DirectChildElementMatcher extends TypeSafeDiagnosingMatcher {

	/**
	 * @var Matcher|null
	 */
	private $matcher;

	public static function havingDirectChild( ?Matcher $elementMatcher = null ) {
		return new static( $elementMatcher );
	}

	public function __construct( ?Matcher $matcher = null ) {
		parent::__construct( DOMNode::class );
		$this->matcher = $matcher;
	}

	public function describeTo( Description $description ) {
		$description->appendText( 'having direct child ' );
		if ( $this->matcher ) {
			$description->appendDescriptionOf( $this->matcher );
		}
	}

	/**
	 * @param DOMDocument|DOMNode $item
	 * @param Description $mismatchDescription
	 *
	 * @return bool
	 */
	protected function matchesSafelyWithDiagnosticDescription(
		$item, Description $mismatchDescription
	) {
		if ( $item instanceof DOMDocument ) {
			$item = $item->documentElement->childNodes->item( 0 );
		}
		$directChildren = $item->childNodes;

		if ( $directChildren->length === 0 ) {
			$mismatchDescription->appendText( 'with no direct children' );
			return false;
		}

		$childWord = $directChildren->length === 1 ? 'child' : 'children';

		$mismatchDescription->appendText( "with direct {$childWord} " );

		if ( !$this->matcher ) {
			return $directChildren->length !== 0;
		}

		$child = null;
		foreach ( $directChildren as $child ) {
			if ( $this->matcher->matches( $child ) ) {
				return true;
			}
		}

		$this->matcher->describeMismatch( $child, $mismatchDescription );

		return false;
	}

}
