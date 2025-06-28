<?php

use Hamcrest\Matcher;
use WMDE\HamcrestHtml\AttributeMatcher;
use WMDE\HamcrestHtml\ChildElementMatcher;
use WMDE\HamcrestHtml\ClassMatcher;
use WMDE\HamcrestHtml\ComplexTagMatcher;
use WMDE\HamcrestHtml\DirectChildElementMatcher;
use WMDE\HamcrestHtml\HtmlMatcher;
use WMDE\HamcrestHtml\RootElementMatcher;
use WMDE\HamcrestHtml\TagNameMatcher;
use WMDE\HamcrestHtml\TextContentsMatcher;

if ( !function_exists( 'htmlPiece' ) ) {
	/**
	 * @param Matcher|null $elementMatcher
	 *
	 * @return HtmlMatcher
	 */
	function htmlPiece( ?Matcher $elementMatcher = null ) {
		return HtmlMatcher::htmlPiece( $elementMatcher );
	}
}

if ( !function_exists( 'havingRootElement' ) ) {
	function havingRootElement( ?Matcher $matcher = null ) {
		return RootElementMatcher::havingRootElement( $matcher );
	}
}

if ( !function_exists( 'havingDirectChild' ) ) {
	function havingDirectChild( ?Matcher $elementMatcher = null ) {
		return DirectChildElementMatcher::havingDirectChild( $elementMatcher );
	}
}

if ( !function_exists( 'havingChild' ) ) {
	function havingChild( ?Matcher $elementMatcher = null ) {
		return ChildElementMatcher::havingChild( $elementMatcher );
	}
}

if ( !function_exists( 'withTagName' ) ) {
	/**
	 * @param Matcher|string $tagName
	 *
	 * @return TagNameMatcher
	 */
	function withTagName( $tagName ) {
		return TagNameMatcher::withTagName( $tagName );
	}
}

if ( !function_exists( 'withAttribute' ) ) {
	/**
	 * @param Matcher|string $attributeName
	 *
	 * @return AttributeMatcher
	 */
	function withAttribute( $attributeName ) {
		return AttributeMatcher::withAttribute( $attributeName );
	}
}

if ( !function_exists( 'withClass' ) ) {
	/**
	 * @param Matcher|string $class
	 *
	 * @return ClassMatcher
	 */
	function withClass( $class ) {
		// TODO don't allow to call with empty string

		return ClassMatcher::withClass( $class );
	}
}

if ( !function_exists( 'havingTextContents' ) ) {
	/**
	 * @param Matcher|string $text
	 *
	 * @return TextContentsMatcher
	 */
	function havingTextContents( $text ) {
		return TextContentsMatcher::havingTextContents( $text );
	}
}

if ( !function_exists( 'tagMatchingOutline' ) ) {
	/**
	 * @param string $htmlOutline
	 *
	 * @return ComplexTagMatcher
	 */
	function tagMatchingOutline( $htmlOutline ) {
		return ComplexTagMatcher::tagMatchingOutline( $htmlOutline );
	}
}
