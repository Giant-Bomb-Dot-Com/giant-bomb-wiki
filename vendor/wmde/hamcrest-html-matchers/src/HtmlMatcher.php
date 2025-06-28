<?php

namespace WMDE\HamcrestHtml;

use DOMDocument;
use Hamcrest\Description;
use Hamcrest\DiagnosingMatcher;
use Hamcrest\Matcher;
use LibXMLError;

class HtmlMatcher extends DiagnosingMatcher {

	/**
	 * @link http://www.xmlsoft.org/html/libxml-xmlerror.html#xmlParserErrors
	 * @link https://github.com/Chronic-Dev/libxml2/blob/683f296a905710ff285c28b8644ef3a3d8be9486/include/libxml/xmlerror.h#L257
	 */
	private const XML_UNKNOWN_TAG_ERROR_CODE = 801;

	/**
	 * @var Matcher|null
	 */
	private $elementMatcher;

	/**
	 * @param Matcher|null $elementMatcher
	 *
	 * @return self
	 */
	public static function htmlPiece( ?Matcher $elementMatcher = null ) {
		return new static( $elementMatcher );
	}

	private function __construct( ?Matcher $elementMatcher = null ) {
		$this->elementMatcher = $elementMatcher;
	}

	public function describeTo( Description $description ) {
		$description->appendText( 'valid html piece ' );
		if ( $this->elementMatcher ) {
			$description->appendDescriptionOf( $this->elementMatcher );
		}
	}

	/**
	 * @param string $html
	 * @param Description $mismatchDescription
	 *
	 * @return bool
	 */
	protected function matchesWithDiagnosticDescription( $html, Description $mismatchDescription ) {
		$internalErrors = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$document = new DOMDocument();

		$html = $this->escapeScriptTagContents( $html );

		// phpcs:ignore Generic.PHP.NoSilencedErrors
		if ( !@$document->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
			$mismatchDescription->appendText( 'there was some parsing error' );
			return false;
		}

		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $internalErrors );

		$result = true;
		foreach ( $errors as $error ) {
			if ( $this->isUnknownTagError( $error ) ) {
				continue;
			}

			$mismatchDescription->appendText( 'there was parsing error: ' )
				->appendText( trim( $error->message ) )
				->appendText( ' on line ' )
				->appendText( (string)$error->line );
			$result = false;
		}

		if ( !$result ) {
			return false;
		}
		$mismatchDescription->appendText( 'valid html piece ' );

		if ( $this->elementMatcher ) {
			$result = $this->elementMatcher->matches( $document );
			$this->elementMatcher->describeMismatch( $document, $mismatchDescription );
		}

		$mismatchDescription->appendText( "\nActual html:\n" )->appendText( $html );

		return $result;
	}

	/**
	 * @param LibXMLError $error
	 *
	 * @return bool
	 */
	private function isUnknownTagError( LibXMLError $error ) {
		return $error->code === self::XML_UNKNOWN_TAG_ERROR_CODE;
	}

	/**
	 * @param string $html
	 *
	 * @return string HTML
	 */
	private function escapeScriptTagContents( $html ) {
		return preg_replace_callback( '#(<script.*>)(.*)(</script>)#isU', static function ( $matches ) {
			return $matches[1] . str_replace( '</', '<\/', $matches[2] ) . $matches[3];
		}, $html );
	}

}
