<?php

namespace WMDE\HamcrestHtml;

use DOMNode;

class XmlNodeRecursiveIterator extends \ArrayIterator {

	public function __construct( \DOMNodeList $nodeList ) {
		$queue = $this->addElementsToQueue( [], $nodeList );
		parent::__construct( $queue );
	}

	/**
	 * @param DOMNode[] $queue
	 * @param \DOMNodeList $nodeList
	 *
	 * @return DOMNode[] New queue
	 */
	private function addElementsToQueue( array $queue, \DOMNodeList $nodeList ) {
		/** @var DOMNode $node */
		foreach ( $nodeList as $node ) {
			$queue[] = $node;
			if ( $node->childNodes !== null ) {
				$queue = $this->addElementsToQueue( $queue, $node->childNodes );
			}
		}

		return $queue;
	}

}
