<?php

namespace WMDE\HamcrestHtml;

use DOMDocument;
use DOMElement;
use Hamcrest\Core\AllOf;
use Hamcrest\Core\IsEqual;
use Hamcrest\Description;
use Hamcrest\Matcher;
use InvalidArgumentException;

class ComplexTagMatcher extends TagMatcher {

	/**
	 * @link http://www.xmlsoft.org/html/libxml-xmlerror.html#xmlParserErrors
	 * @link https://github.com/Chronic-Dev/libxml2/blob/683f296a905710ff285c28b8644ef3a3d8be9486/include/libxml/xmlerror.h#L257
	 */
	private const XML_UNKNOWN_TAG_ERROR_CODE = 801;

	/**
	 * @var string
	 */
	private $tagHtmlOutline;

	/**
	 * @var Matcher
	 */
	private $matcher;

	/**
	 * @param string $htmlOutline
	 *
	 * @return self
	 */
	public static function tagMatchingOutline( $htmlOutline ) {
		return new self( $htmlOutline );
	}

	/**
	 * @param string $tagHtmlRepresentation
	 */
	public function __construct( $tagHtmlRepresentation ) {
		parent::__construct();

		$this->tagHtmlOutline = $tagHtmlRepresentation;
		$this->matcher = $this->createMatcherFromHtml( $tagHtmlRepresentation );
	}

	/**
	 * @param Description $description
	 */
	public function describeTo( Description $description ) {
		$description->appendText( 'tag matching outline `' )
			->appendText( $this->tagHtmlOutline )
			->appendText( '` ' );
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
		if ( $this->matcher->matches( $item ) ) {
			return true;
		}

		$mismatchDescription->appendText( 'was `' )
			->appendText( $this->elementToString( $item ) )
			->appendText( '`' );
		return false;
	}

	/**
	 * @param string $htmlOutline
	 *
	 * @return Matcher
	 */
	private function createMatcherFromHtml( $htmlOutline ) {
		$document = $this->parseHtml( $htmlOutline );
		$targetTag = $this->getSingleTagFromThe( $document );

		$this->assertTagDoesNotContainChildren( $targetTag );

		$attributeMatchers = $this->createAttributeMatchers( $htmlOutline, $targetTag );
		$classMatchers = $this->createClassMatchers( $targetTag );

		return AllOf::allOf(
			new TagNameMatcher( IsEqual::equalTo( $targetTag->tagName ) ),
			call_user_func_array( [ AllOf::class, 'allOf' ], $attributeMatchers ),
			call_user_func_array( [ AllOf::class, 'allOf' ], $classMatchers )
		);
	}

	/**
	 * @param \LibXMLError $error
	 *
	 * @return bool
	 */
	private function isUnknownTagError( \LibXMLError $error ) {
		return $error->code === self::XML_UNKNOWN_TAG_ERROR_CODE;
	}

	/**
	 * @param string $inputHtml
	 * @param string $attributeName
	 *
	 * @return bool
	 */
	private function isBooleanAttribute( $inputHtml, $attributeName ) {
		$quotedName = preg_quote( $attributeName, '/' );

		return !preg_match( "/\b{$quotedName}\s*=/ui", $inputHtml );
	}

	/**
	 * @param string $html
	 *
	 * @return DOMDocument
	 * @throws InvalidArgumentException
	 */
	private function parseHtml( $html ) {
		$internalErrors = libxml_use_internal_errors( true );
		$document = new DOMDocument();

		// phpcs:ignore Generic.PHP.NoSilencedErrors
		if ( !@$document->loadHTML( $html ) ) {
			throw new InvalidArgumentException( "There was some parsing error of `$html`" );
		}

		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $internalErrors );

		foreach ( $errors as $error ) {
			if ( $this->isUnknownTagError( $error ) ) {
				continue;
			}

			throw new InvalidArgumentException(
				'There was parsing error: ' . trim( $error->message ) . ' on line ' . $error->line
			);
		}

		return $document;
	}

	/**
	 * @param DOMDocument $document
	 *
	 * @return DOMElement
	 * @throws InvalidArgumentException
	 */
	private function getSingleTagFromThe( DOMDocument $document ) {
		$directChildren = $document->documentElement->childNodes->item( 0 )->childNodes;

		if ( $directChildren->length !== 1 ) {
			throw new InvalidArgumentException(
				'Expected exactly 1 tag description, got ' . $directChildren->length
			);
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnNullable
		return $directChildren->item( 0 );
	}

	private function assertTagDoesNotContainChildren( DOMElement $targetTag ) {
		if ( $targetTag->childNodes->length > 0 ) {
			throw new InvalidArgumentException( 'Nested elements are not allowed' );
		}
	}

	/**
	 * @param string $inputHtml
	 * @param DOMElement $targetTag
	 *
	 * @return AttributeMatcher[]
	 */
	private function createAttributeMatchers( $inputHtml, DOMElement $targetTag ) {
		$attributeMatchers = [];
		/** @var \DOMAttr $attribute */
		foreach ( $targetTag->attributes as $attribute ) {
			if ( $attribute->name === 'class' ) {
				continue;
			}

			$attributeMatcher = new AttributeMatcher( IsEqual::equalTo( $attribute->name ) );
			if ( !$this->isBooleanAttribute( $inputHtml, $attribute->name ) ) {
				$attributeMatcher = $attributeMatcher->havingValue(
					IsEqual::equalTo( $attribute->value )
				);
			}

			$attributeMatchers[] = $attributeMatcher;
		}
		return $attributeMatchers;
	}

	/**
	 * @param DOMElement $targetTag
	 *
	 * @return ClassMatcher[]
	 */
	private function createClassMatchers( DOMElement $targetTag ) {
		$classMatchers = [];
		$classValue = $targetTag->getAttribute( 'class' );
		foreach ( explode( ' ', $classValue ) as $expectedClass ) {
			if ( $expectedClass === '' ) {
				continue;
			}
			$classMatchers[] = new ClassMatcher( IsEqual::equalTo( $expectedClass ) );
		}
		return $classMatchers;
	}

	/**
	 * @param DOMElement $element
	 *
	 * @return string
	 */
	private function elementToString( DOMElement $element ) {
		$newDocument = new DOMDocument();
		$cloned = $element->cloneNode( true );
		$newDocument->appendChild( $newDocument->importNode( $cloned, true ) );
		return trim( $newDocument->saveHTML() );
	}

}
