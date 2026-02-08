<?php

class MetaTagHooks {
	/**
	 * Sets up the MetaTag Parser hook and system messages
	 *
	 * @param Parser $parser
	 *
	 * @return bool true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'gbmeta', [ __CLASS__, 'renderMetaTag' ] );

		return true;
	}


	/**
	 * Renders the <metadesc> tag.
	 *
	 * @param String $text The description to output
	 * @param array $params Attributes specified for the tag. Should be an empty array
	 * @param Parser $parser Reference to currently running parser
	 *
	 * @return String Always empty (because we don't output anything to the text).
	 */
	public static function renderMetaTag( $text, $params, Parser $parser, PPFrame $frame ) {
        // IGNORE $text. It's too unreliable with {{#tag}}.
        // Instead, we look specifically for our named keys in $params.
        
        $attrType    = isset( $params['type'] ) ? trim( $params['type'] ) : 'name';
        $attrValue   = isset( $params['name'] ) ? trim( $params['name'] ) : 'description';
        $contentBody = isset( $params['content'] ) ? trim( $params['content'] ) : '';

        // If 'content' wasn't used, but a message was passed as a raw parameter
        if ( $contentBody === '' && $text !== '' ) {
            $contentBody = trim( $text );
        }

        if ( $contentBody === '' ) {
            return '';
        }

        $data = $parser->getOutput()->getExtensionData( 'gbmeta_tags' ) ?? [];
        
        $data[] = [
            'type'    => $attrType,  // 'property' or 'name'
            'label'   => $attrValue, // 'og:test'
            'content' => $contentBody
        ];

        $parser->getOutput()->setExtensionData( 'gbmeta_tags', $data );
        return '';
    }



	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parseroutput ) {
        $tags = $parseroutput->getExtensionData( 'gbmeta_tags' );
        
        if ( is_array( $tags ) ) {
            foreach ( $tags as $tag ) {
                // This builds: <meta property="og:test" content="This is the description">
                $html = Html::element( 'meta', [
                    $tag['type'] => $tag['label'],
                    'content'    => $tag['content']
                ] );

                $out->addHeadItem( 'gbmeta-' . md5( $html ), $html . "\n" );
            }
        }
        return true;
    }
}

