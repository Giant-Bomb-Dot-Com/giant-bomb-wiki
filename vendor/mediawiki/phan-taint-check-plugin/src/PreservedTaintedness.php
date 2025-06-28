<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;

/**
 * This class represents what taintedness is passed through (=preserved) by a function parameter
 */
class PreservedTaintedness {
	/** @var ParamLinksOffsets */
	private $ownOffsets;

	/** @var self[] Taintedness for each possible array element */
	private $dimTaint = [];

	/** @var ParamLinksOffsets|null */
	private $keysOffsets;

	/**
	 * @var self|null Taintedness for array elements that we couldn't attribute to any key
	 */
	private $unknownDimsTaint;

	/**
	 * @param ParamLinksOffsets $offsets
	 */
	public function __construct( ParamLinksOffsets $offsets ) {
		$this->ownOffsets = $offsets;
	}

	/**
	 * @return self
	 */
	public static function newEmpty(): self {
		return new self( ParamLinksOffsets::newEmpty() );
	}

	/**
	 * Set the taintedness for $offset to $value, in place
	 *
	 * @param Node|mixed $offset Node or a scalar value, already resolved
	 * @param self $value
	 */
	public function setOffsetTaintedness( $offset, self $value ): void {
		if ( is_scalar( $offset ) ) {
			$this->dimTaint[$offset] = $value;
		} else {
			$this->unknownDimsTaint ??= self::newEmpty();
			$this->unknownDimsTaint->mergeWith( $value );
		}
	}

	/**
	 * @param ParamLinksOffsets $offsets
	 */
	public function setKeysOffsets( ParamLinksOffsets $offsets ): void {
		$this->keysOffsets = $offsets;
	}

	/**
	 * @param self $other
	 */
	public function mergeWith( self $other ): void {
		$this->ownOffsets->mergeWith( $other->ownOffsets );
		if ( $other->keysOffsets && !$this->keysOffsets ) {
			$this->keysOffsets = $other->keysOffsets;
		} elseif ( $other->keysOffsets ) {
			$this->keysOffsets->mergeWith( $other->keysOffsets );
		}

		if ( $other->unknownDimsTaint && !$this->unknownDimsTaint ) {
			$this->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		foreach ( $other->dimTaint as $key => $val ) {
			if ( !array_key_exists( $key, $this->dimTaint ) ) {
				$this->dimTaint[$key] = clone $val;
			} else {
				$this->dimTaint[$key]->mergeWith( $val );
			}
		}
	}

	/**
	 * @param self $other
	 * @return self
	 * @suppress PhanUnreferencedPublicMethod Kept for consistency
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	/**
	 * @param Taintedness $argTaint
	 * @return Taintedness
	 */
	public function asTaintednessForArgument( Taintedness $argTaint ): Taintedness {
		$ret = $this->ownOffsets->appliedToTaintedness( $argTaint );

		foreach ( $this->dimTaint as $k => $val ) {
			$ret->setOffsetTaintedness( $k, $val->asTaintednessForArgument( $argTaint ) );
		}
		if ( $this->unknownDimsTaint ) {
			$ret->setOffsetTaintedness( null, $this->unknownDimsTaint->asTaintednessForArgument( $argTaint ) );
		}
		if ( $this->keysOffsets ) {
			$ret->addKeysTaintedness( $this->keysOffsets->appliedToTaintedness( $argTaint )->get() );
		}
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness suitable for the debug annotation
	 *
	 * @return string
	 */
	public function toShortString(): string {
		$ret = "{Own: " . $this->ownOffsets->__toString();
		if ( $this->keysOffsets ) {
			$ret .= '; Keys: ' . $this->keysOffsets->__toString();
		}
		$keyParts = [];
		if ( $this->dimTaint ) {
			foreach ( $this->dimTaint as $key => $taint ) {
				$keyParts[] = "$key => " . $taint->toShortString();
			}
		}
		if ( $this->unknownDimsTaint ) {
			$keyParts[] = 'Unknown => ' . $this->unknownDimsTaint->toShortString();
		}
		if ( $keyParts ) {
			$ret .= '; Elements: {' . implode( '; ', $keyParts ) . '}';
		}
		$ret .= '}';
		return $ret;
	}

	/**
	 * Make sure to clone member variables, too.
	 */
	public function __clone() {
		$this->ownOffsets = clone $this->ownOffsets;
		if ( $this->unknownDimsTaint ) {
			$this->unknownDimsTaint = clone $this->unknownDimsTaint;
		}
		foreach ( $this->dimTaint as $k => $v ) {
			$this->dimTaint[$k] = clone $v;
		}
	}

	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->toShortString();
	}
}
