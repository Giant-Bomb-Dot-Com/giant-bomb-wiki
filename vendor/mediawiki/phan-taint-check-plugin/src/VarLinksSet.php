<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use Phan\Language\Element\TypedElementInterface;
use Phan\Library\Set;

/**
 * Convenience class for better type inference.
 *
 * @method PreservedTaintedness offsetGet( \Phan\Language\Element\TypedElementInterface $object )
 * @method offsetSet( \Phan\Language\Element\TypedElementInterface $object, PreservedTaintedness $data )
 * @method void attach(TypedElementInterface $object, PreservedTaintedness $data)
 * @method TypedElementInterface current()
 * @phan-file-suppress PhanParamSignatureMismatch,PhanParamSignaturePHPDocMismatchParamType
 * @phan-file-suppress PhanParamSignaturePHPDocMismatchTooManyRequiredParameters
 */
class VarLinksSet extends Set {
	public function __toString(): string {
		$children = [];
		foreach ( $this as $var ) {
			$children[] = $var->getName() . ': ' . $this[$var]->toShortString();
		}
		return '[' . implode( ',', $children ) . ']';
	}

	public function __clone() {
		foreach ( $this as $var ) {
			$this[$var] = clone $this[$var];
		}
	}
}
