<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use Phan\Language\Element\FunctionInterface;
use Phan\Library\Set;

/**
 * Convenience class for better type inference.
 *
 * @method SingleMethodLinks offsetGet( \Phan\Language\Element\FunctionInterface $object )
 * @method offsetSet( \Phan\Language\Element\FunctionInterface $object, SingleMethodLinks $data )
 * @method void attach(FunctionInterface $object, SingleMethodLinks $data)
 * @method FunctionInterface current()
 * @phan-file-suppress PhanParamSignatureMismatch,PhanParamSignaturePHPDocMismatchParamType
 * @phan-file-suppress PhanParamSignaturePHPDocMismatchTooManyRequiredParameters
 */
class LinksSet extends Set {
	/**
	 * @param self $other
	 */
	public function mergeWith( self $other ): void {
		foreach ( $other as $method ) {
			if ( $this->contains( $method ) ) {
				$this[$method]->mergeWith( $other[$method] );
			} else {
				$this->attach( $method, $other[$method] );
			}
		}
	}

	/**
	 * @param LinksSet $other
	 * @return self
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	/**
	 * @return self
	 */
	public function asAllMovedToKeys(): self {
		$ret = new self;
		foreach ( $this as $func ) {
			$ret[$func] = $this[$func]->asAllParamsMovedToKeys();
		}
		return $ret;
	}

	public function __toString(): string {
		$children = [];
		foreach ( $this as $func ) {
			$children[] = $func->getFQSEN()->__toString() . ': ' . $this[$func]->__toString();
		}
		return '{ ' . implode( ',', $children ) . ' }';
	}

	public function __clone() {
		foreach ( $this as $func ) {
			$this[$func] = clone $this[$func];
		}
	}
}
