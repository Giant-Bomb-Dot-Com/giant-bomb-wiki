<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;

/**
 * Value object used to store taintedness. This should always be used to manipulate taintedness values,
 * instead of directly using taint constants directly (except for comparisons etc.).
 *
 * Note that this class should be used as copy-on-write (like phan's UnionType), so in-place
 * manipulation should never be done on phan objects.
 */
class Taintedness {
	/** @var int Combination of the class constants */
	private $flags;

	/** @var self[] Taintedness for each possible array element */
	private $dimTaint = [];

	/** @var int Taintedness of the array keys */
	private $keysTaint = SecurityCheckPlugin::NO_TAINT;

	/**
	 * @var self|null Taintedness for array elements that we couldn't attribute to any key
	 */
	private $unknownDimsTaint;

	/**
	 * @param int $val One of the class constants
	 */
	public function __construct( int $val ) {
		$this->flags = $val;
	}

	// Common creation shortcuts

	/**
	 * @return self
	 */
	public static function newSafe(): self {
		return new self( SecurityCheckPlugin::NO_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newUnknown(): self {
		return new self( SecurityCheckPlugin::UNKNOWN_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newTainted(): self {
		return new self( SecurityCheckPlugin::YES_TAINT );
	}

	/**
	 * @param Taintedness[] $values
	 * @return self
	 */
	public static function newFromArray( array $values ): self {
		$ret = self::newSafe();
		foreach ( $values as $key => $value ) {
			assert( $value instanceof self );
			$ret->setOffsetTaintedness( $key, $value );
		}
		return $ret;
	}

	/**
	 * Get a numeric representation of the taint stored in this object. This includes own taint,
	 * array keys and whatnot.
	 * @note This should almost NEVER be used outside of this class! Use accessors as much as possible!
	 *
	 * @return int
	 */
	public function get(): int {
		$ret = $this->flags | $this->getAllKeysTaint() | $this->keysTaint;
		return $this->unknownDimsTaint ? ( $ret | $this->unknownDimsTaint->get() ) : $ret;
	}

	/**
	 * Get a flattened version of this object, with any taint from keys etc. collapsed into flags
	 * @return $this
	 */
	public function asCollapsed(): self {
		return new self( $this->get() );
	}

	/**
	 * Returns a copy of this object where the taintedness of every known key has been reassigned
	 * to unknown keys.
	 * @return self
	 */
	public function asKnownKeysMadeUnknown(): self {
		$ret = new self( $this->flags );
		$ret->keysTaint = $this->keysTaint;
		$ret->unknownDimsTaint = $this->unknownDimsTaint;
		if ( $this->dimTaint ) {
			$ret->unknownDimsTaint ??= self::newSafe();
			foreach ( $this->dimTaint as $keyTaint ) {
				$ret->unknownDimsTaint->mergeWith( $keyTaint );
			}
		}
		return $ret;
	}

	/**
	 * Recursively extract the taintedness from each key.
	 *
	 * @return int
	 */
	private function getAllKeysTaint(): int {
		$ret = SecurityCheckPlugin::NO_TAINT;
		foreach ( $this->dimTaint as $val ) {
			$ret |= $val->get();
		}
		return $ret;
	}

	// Value manipulation

	/**
	 * Add the given taint to this object's flags, *without* creating a clone
	 * @see Taintedness::with() if you need a clone
	 * @see Taintedness::mergeWith() if you want to preserve the whole shape
	 *
	 * @param int $taint
	 */
	public function add( int $taint ): void {
		// TODO: Should this clear UNKNOWN_TAINT if its present only in one of the args?
		$this->flags |= $taint;
	}

	/**
	 * Returns a copy of this object, with the bits in $other added to flags.
	 * @see Taintedness::add() for the in-place version
	 * @see Taintedness::asMergedWith() if you want to preserve the whole shape
	 *
	 * @param int $other
	 * @return $this
	 */
	public function with( int $other ): self {
		$ret = clone $this;
		$ret->add( $other );
		return $ret;
	}

	/**
	 * Recursively remove the given taint from this object, *without* creating a clone
	 * @see Taintedness::without() if you need a clone
	 *
	 * @param int $other
	 */
	public function remove( int $other ): void {
		$this->keepOnly( ~$other );
	}

	/**
	 * Returns a copy of this object, with the bits in $other removed recursively.
	 * @see Taintedness::remove() for the in-place version
	 *
	 * @param int $other
	 * @return $this
	 */
	public function without( int $other ): self {
		$ret = clone $this;
		$ret->remove( $other );
		return $ret;
	}

	/**
	 * Check whether this object has the given flag, recursively.
	 * @note If $taint has more than one flag, this will check for at least one, not all.
	 *
	 * @param int $taint
	 * @return bool
	 */
	public function has( int $taint ): bool {
		// Avoid using get() for performance
		if ( ( $this->flags & $taint ) !== SecurityCheckPlugin::NO_TAINT ) {
			return true;
		}
		if ( ( $this->keysTaint & $taint ) !== SecurityCheckPlugin::NO_TAINT ) {
			return true;
		}
		if ( $this->unknownDimsTaint && $this->unknownDimsTaint->has( $taint ) ) {
			return true;
		}
		foreach ( $this->dimTaint as $val ) {
			if ( $val->has( $taint ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep only the taint in $taint, recursively, preserving the shape and without creating a copy.
	 * @see Taintedness::withOnly if you need a clone
	 *
	 * @param int $taint
	 */
	public function keepOnly( int $taint ): void {
		$this->flags &= $taint;
		if ( $this->unknownDimsTaint ) {
			$this->unknownDimsTaint->keepOnly( $taint );
		}
		$this->keysTaint &= $taint;
		foreach ( $this->dimTaint as $val ) {
			$val->keepOnly( $taint );
		}
	}

	/**
	 * Returns a copy of this object, with only the taint in $taint kept (recursively, preserving the shape)
	 * @see Taintedness::keepOnly() for the in-place version
	 *
	 * @param int $other
	 * @return $this
	 */
	public function withOnly( int $other ): self {
		$ret = clone $this;
		$ret->keepOnly( $other );
		return $ret;
	}

	/**
	 * Intersect the taintedness of a value against that of a sink, to later determine whether the
	 * expression is safe. In case of function calls, $sink is the param taint and $value is the arg taint.
	 *
	 * @note The order of the arguments is important! This method preserves the shape of $sink, not $value.
	 *
	 * @note The order of the arguments is important! This method preserves the shape of $sink, not $value.
	 *
	 * @param Taintedness $sink
	 * @param Taintedness $value
	 * @return self
	 */
	public static function intersectForSink( self $sink, self $value ): self {
		$intersect = new self( SecurityCheckPlugin::NO_TAINT );
		// If the sink has non-zero flags, intersect it with the whole other side. This particularly preserves
		// the shape of $sink, discarding anything from $value if the sink has a NO_TAINT in that position.
		if ( $sink->flags & SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ) {
			// Special case: NUMKEY is only for the outer array
			$rightFlags = $value->flags | $value->keysTaint;
			if ( $value->keysTaint & SecurityCheckPlugin::SQL_TAINT ) {
				// FIXME HACK: If keys are tainted, add numkey. This assumes that numkey is really only used for
				// Database methods, where keys are never escaped.
				$rightFlags |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			$rightFlags |= ( $value->getAllKeysTaint() & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
			if ( $value->unknownDimsTaint ) {
				$rightFlags |= $value->unknownDimsTaint->get() & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			$intersect->flags = $sink->flags & ( ( $rightFlags & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		} elseif ( $sink->flags ) {
			$intersect->flags = $sink->flags & ( ( $value->get() & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		}
		if ( $sink->unknownDimsTaint ) {
			$intersect->unknownDimsTaint = self::intersectForSink(
				$sink->unknownDimsTaint,
				$value->asValueFirstLevel()
			);
		}
		$valueKeysAsExec = ( ( $value->keysTaint | $value->flags ) & SecurityCheckPlugin::ALL_TAINT ) << 1;
		$intersect->keysTaint = $sink->keysTaint & $valueKeysAsExec;
		foreach ( $sink->dimTaint as $key => $dTaint ) {
			$intersect->dimTaint[$key] = self::intersectForSink(
				$dTaint,
				$value->getTaintednessForOffsetOrWhole( $key )
			);
		}
		return $intersect;
	}

	/**
	 * Removes offset data from $this for all known offsets of $other, in place.
	 *
	 * @param Taintedness $other
	 * @return void
	 */
	public function removeKnownKeysFrom( self $other ): void {
		foreach ( $other->dimTaint as $key => $_ ) {
			unset( $this->dimTaint[$key] );
		}
		if (
			( $this->flags & SecurityCheckPlugin::SQL_NUMKEY_TAINT ) &&
			!$this->has( SecurityCheckPlugin::SQL_TAINT )
		) {
			// Note that this adjustment is not guaranteed to happen immediately after the removal of the last
			// integer key. For instance, in [ 0 => unsafe, 'foo' => unsafe ], if only the element 0 is removed,
			// this branch will not run because 'foo' still contributes sql taint.
			$this->flags &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
	}

	/**
	 * Merge this object with $other, recursively and without creating a copy.
	 * @see Taintedness::asMergedWith() if you need a copy
	 *
	 * @param Taintedness $other
	 */
	public function mergeWith( self $other ): void {
		$this->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$this->unknownDimsTaint ) {
			$this->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		$this->keysTaint |= $other->keysTaint;
		foreach ( $other->dimTaint as $key => $val ) {
			if ( !isset( $this->dimTaint[$key] ) ) {
				$this->dimTaint[$key] = clone $val;
			} else {
				$this->dimTaint[$key]->mergeWith( $val );
			}
		}
	}

	/**
	 * Merge this object with $other, recursively, creating a copy.
	 * @see Taintedness::mergeWith() for in-place merge
	 *
	 * @param Taintedness $other
	 * @return $this
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	// Offsets taintedness

	/**
	 * Set the taintedness for $offset to $value, in place
	 *
	 * @param Node|mixed $offset Node or a scalar value, already resolved
	 * @param Taintedness $value
	 */
	public function setOffsetTaintedness( $offset, self $value ): void {
		if ( is_scalar( $offset ) ) {
			$this->dimTaint[$offset] = $value;
		} else {
			$this->unknownDimsTaint ??= self::newSafe();
			$this->unknownDimsTaint->mergeWith( $value );
		}
	}

	/**
	 * Adds the bits in $value to the taintedness of the keys
	 * @param int $value
	 */
	public function addKeysTaintedness( int $value ): void {
		$this->keysTaint |= $value;
	}

	/**
	 * @param self $other
	 * @param int $depth
	 * @return self
	 */
	public function asMergedForAssignment( self $other, int $depth ): self {
		if ( $depth === 0 ) {
			return $other;
		}
		$ret = clone $this;
		$ret->flags |= $other->flags;
		$ret->keysTaint |= $other->keysTaint;
		if ( !$ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$ret->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		foreach ( $other->dimTaint as $k => $v ) {
			$ret->dimTaint[$k] = isset( $ret->dimTaint[$k] )
				? $ret->dimTaint[$k]->asMergedForAssignment( $v, $depth - 1 )
				: $v;
		}
		return $ret;
	}

	/**
	 * Apply an array addition with $other
	 *
	 * @param Taintedness $other
	 */
	public function arrayPlus( self $other ): void {
		$this->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$this->unknownDimsTaint ) {
			$this->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		$this->keysTaint |= $other->keysTaint;
		// This is not recursive because array addition isn't
		$this->dimTaint += $other->dimTaint;
	}

	/**
	 * Apply the effect of array addition and return a clone of $this
	 *
	 * @param Taintedness $other
	 * @return $this
	 */
	public function asArrayPlusWith( self $other ): self {
		$ret = clone $this;
		$ret->arrayPlus( $other );
		return $ret;
	}

	/**
	 * Get the taintedness for the given offset, if set. If $offset could not be resolved, this
	 * will return the whole object, with taint from unknown keys added. If the offset is not known,
	 * it will return a new Taintedness object without the original shape, and with taint from
	 * unknown keys added.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @return self Always a copy
	 */
	public function getTaintednessForOffsetOrWhole( $offset ): self {
		if ( !is_scalar( $offset ) ) {
			return $this->asValueFirstLevel();
		}
		if ( isset( $this->dimTaint[$offset] ) ) {
			if ( $this->unknownDimsTaint ) {
				$ret = $this->dimTaint[$offset]->asMergedWith( $this->unknownDimsTaint );
			} else {
				$ret = $this->dimTaint[$offset];
			}
		} elseif ( $this->unknownDimsTaint ) {
			$ret = $this->unknownDimsTaint;
		} else {
			return new self( $this->flags );
		}
		$ret->flags |= $this->flags;
		return $ret;
	}

	/**
	 * Create a new object with $this at the given $offset (if scalar) or as unknown object.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @param int|null $offsetTaint If available, will be used as key taint
	 * @return self Always a copy
	 */
	public function asMaybeMovedAtOffset( $offset, int $offsetTaint = null ): self {
		$ret = self::newSafe();
		if ( $offsetTaint !== null ) {
			$ret->keysTaint = $offsetTaint;
		}
		if ( $offset instanceof Node || $offset === null ) {
			$ret->unknownDimsTaint = clone $this;
		} else {
			$ret->dimTaint[$offset] = clone $this;
		}
		return $ret;
	}

	/**
	 * Get a representation of this taint at the first depth level. For instance, this can be used in a foreach
	 * assignment for the value. Own taint and unknown keys taint are preserved, and then we merge in recursively
	 * all the current keys.
	 *
	 * @return $this
	 */
	public function asValueFirstLevel(): self {
		$ret = new self( $this->flags & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
		if ( $this->unknownDimsTaint ) {
			$ret->mergeWith( $this->unknownDimsTaint );
		}
		foreach ( $this->dimTaint as $val ) {
			$ret->mergeWith( $val );
		}
		return $ret;
	}

	/**
	 * Creates a copy of this object without the given key
	 * @param string|int|bool|float $key
	 * @return $this
	 */
	public function withoutKey( $key ): self {
		$ret = clone $this;
		unset( $ret->dimTaint[$key] );
		if (
			( $ret->flags & SecurityCheckPlugin::SQL_NUMKEY_TAINT ) &&
			!$ret->has( SecurityCheckPlugin::SQL_TAINT )
		) {
			// Note that this adjustment is not guaranteed to happen immediately after the removal of the last
			// integer key. For instance, in [ 0 => unsafe, 'foo' => unsafe ], if the element 0 is removed,
			// this branch will not run because 'foo' still contributes sql taint.
			$ret->flags &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
		}
		return $ret;
	}

	/**
	 * Creates a copy of this object without known offsets, and without keysTaint
	 * @return $this
	 */
	public function withoutKeys(): self {
		$ret = clone $this;
		$ret->keysTaint = SecurityCheckPlugin::NO_TAINT;
		if ( !$ret->dimTaint ) {
			return $ret;
		}
		$ret->unknownDimsTaint ??= self::newSafe();
		foreach ( $ret->dimTaint as $dim => $taint ) {
			$ret->unknownDimsTaint->mergeWith( $taint );
			unset( $ret->dimTaint[$dim] );
		}
		return $ret;
	}

	/**
	 * Get a representation of this taint to be used in a foreach assignment for the key
	 *
	 * @return $this
	 */
	public function asKeyForForeach(): self {
		return new self( ( $this->keysTaint | $this->flags ) & ~SecurityCheckPlugin::SQL_NUMKEY_TAINT );
	}

	/**
	 * Applies an array_replace operations to $this, in place.
	 *
	 * @param Taintedness $other
	 * @return void
	 */
	public function arrayReplace( self $other ): void {
		$this->flags |= $other->flags;
		$this->dimTaint = array_replace( $this->dimTaint, $other->dimTaint );
		if ( $other->unknownDimsTaint ) {
			if ( $this->unknownDimsTaint ) {
				$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
			} else {
				$this->unknownDimsTaint = $other->unknownDimsTaint;
			}
		}
	}

	/**
	 * Applies an array_merge operations to $this, in place.
	 *
	 * @param Taintedness $other
	 * @return void
	 */
	public function arrayMerge( self $other ): void {
		// First merge the known elements
		$this->dimTaint = array_merge( $this->dimTaint, $other->dimTaint );
		// Then merge general flags, key flags, and any unknown keys
		$this->flags |= $other->flags;
		$this->keysTaint |= $other->keysTaint;
		$this->unknownDimsTaint ??= new self( SecurityCheckPlugin::NO_TAINT );
		if ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		// Finally, move taintedness from int keys to unknown
		foreach ( $this->dimTaint as $k => $val ) {
			if ( is_int( $k ) ) {
				$this->unknownDimsTaint->mergeWith( $val );
				unset( $this->dimTaint[$k] );
			}
		}
	}

	// Conversion/checks shortcuts

	/**
	 * Check whether this object has no taintedness.
	 *
	 * @return bool
	 */
	public function isSafe(): bool {
		// Don't use get() for performance
		if ( $this->flags !== SecurityCheckPlugin::NO_TAINT ) {
			return false;
		}
		if ( $this->keysTaint !== SecurityCheckPlugin::NO_TAINT ) {
			return false;
		}
		if ( $this->unknownDimsTaint && !$this->unknownDimsTaint->isSafe() ) {
			return false;
		}
		foreach ( $this->dimTaint as $val ) {
			if ( !$val->isSafe() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Convert exec to yes taint recursively. Special flags like UNKNOWN or INAPPLICABLE are discarded.
	 * Any YES flags are also discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @warning This function is nilpotent: f^2(x) = 0
	 *
	 * @return self
	 */
	public function asExecToYesTaint(): self {
		$ret = new self( ( $this->flags & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1 );
		if ( $this->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $this->unknownDimsTaint->asExecToYesTaint();
		}
		$ret->keysTaint = ( $this->keysTaint & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
		foreach ( $this->dimTaint as $k => $val ) {
			$ret->dimTaint[$k] = $val->asExecToYesTaint();
		}
		return $ret;
	}

	/**
	 * Convert the yes taint bits to corresponding exec taint bits recursively.
	 * Any UNKNOWN_TAINT or INAPPLICABLE_TAINT is discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @warning This function is nilpotent: f^2(x) = 0
	 *
	 * @return self
	 * @suppress PhanUnreferencedPublicMethod For consistency
	 */
	public function asYesToExecTaint(): self {
		$ret = new self( ( $this->flags & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		if ( $this->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $this->unknownDimsTaint->asYesToExecTaint();
		}
		$ret->keysTaint = ( $this->keysTaint & SecurityCheckPlugin::ALL_TAINT ) << 1;
		foreach ( $this->dimTaint as $k => $val ) {
			$ret->dimTaint[$k] = $val->asYesToExecTaint();
		}
		return $ret;
	}

	/**
	 * @param ParamLinksOffsets $offsets
	 * @return self
	 */
	public function asMovedAtRelevantOffsetsForBackprop( ParamLinksOffsets $offsets ): self {
		$offsetsFlags = $offsets->getFlags();
		$ret = $offsetsFlags ?
			$this->withOnly( ( $offsetsFlags & SecurityCheckPlugin::ALL_TAINT ) << 1 )
			: new self( SecurityCheckPlugin::NO_TAINT );
		foreach ( $offsets->getDims() as $k => $val ) {
			$newVal = $this->asMovedAtRelevantOffsetsForBackprop( $val );
			if ( isset( $ret->dimTaint[$k] ) ) {
				$ret->dimTaint[$k]->mergeWith( $newVal );
			} else {
				$ret->dimTaint[$k] = $newVal;
			}
		}
		$unknownOffs = $offsets->getUnknown();
		if ( $unknownOffs ) {
			$newVal = $this->asMovedAtRelevantOffsetsForBackprop( $unknownOffs );
			if ( $ret->unknownDimsTaint ) {
				$ret->unknownDimsTaint->mergeWith( $newVal );
			} else {
				$ret->unknownDimsTaint = $newVal;
			}
		}
		$ret->keysTaint |= ( $this->flags | $this->keysTaint ) &
			( ( $offsets->getKeysFlags() & SecurityCheckPlugin::ALL_TAINT ) << 1 );
		return $ret;
	}

	/**
	 * Utility method to convert some flags from EXEC to YES. Note that this is not used internally
	 * to avoid the unnecessary overhead of a function call in hot code.
	 *
	 * @param int $flags
	 * @return int
	 */
	public static function flagsAsExecToYesTaint( int $flags ): int {
		return ( $flags & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
	}

	/**
	 * Utility method to convert some flags from YES to EXEC. Note that this is not used internally
	 * to avoid the unnecessary overhead of a function call in hot code.
	 *
	 * @param int $flags
	 * @return int
	 */
	public static function flagsAsYesToExecTaint( int $flags ): int {
		return ( $flags & SecurityCheckPlugin::ALL_TAINT ) << 1;
	}

	/**
	 * @todo This method shouldn't be necessary (ideally)
	 * @return PreservedTaintedness
	 */
	public function asPreservedTaintedness(): PreservedTaintedness {
		$ret = new PreservedTaintedness( new ParamLinksOffsets( $this->flags ) );
		foreach ( $this->dimTaint as $k => $val ) {
			$ret->setOffsetTaintedness( $k, $val->asPreservedTaintedness() );
		}
		if ( $this->unknownDimsTaint ) {
			$ret->setOffsetTaintedness( null, $this->unknownDimsTaint->asPreservedTaintedness() );
		}
		return $ret;
	}

	/**
	 * Given some method links, returns a list of pairs of LinksSet and Taintedness objects, where the taintedness
	 * in each pair should be backpropagated to the links in the LinksSet.
	 *
	 * @param MethodLinks $links
	 * @return array<array<LinksSet|Taintedness>>
	 * @phan-return array<array{0:LinksSet,1:Taintedness}>
	 */
	public function decomposeForLinks( MethodLinks $links ): array {
		$pairs = [];

		if ( $this->flags !== SecurityCheckPlugin::NO_TAINT ) {
			$pairs[] = [ $links->getLinksCollapsing(), new self( $this->flags ) ];
		}

		if ( $this->keysTaint !== SecurityCheckPlugin::NO_TAINT ) {
			$pairs[] = [ $links->asKeyForForeach()->getLinksCollapsing(), $this->asKeyForForeach() ];
		}

		foreach ( $this->dimTaint as $k => $dimTaint ) {
			$pairs = array_merge(
				$pairs,
				$dimTaint->decomposeForLinks( $links->getForDim( $k ) )
			);
		}

		if ( $this->unknownDimsTaint ) {
			$pairs = array_merge(
				$pairs,
				$this->unknownDimsTaint->decomposeForLinks( $links->getForDim( null ) )
			);
		}
		return $pairs;
	}

	/**
	 * Get a stringified representation of this taintedness, useful for debugging etc.
	 *
	 * @param string $indent
	 * @return string
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function toString( $indent = '' ): string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$keys = SecurityCheckPlugin::taintToString( $this->keysTaint );
		$ret = <<<EOT
{
$indent    Own taint: $flags
$indent    Keys: $keys
$indent    Elements: {
EOT;

		$kIndent = "$indent    ";
		$first = "\n";
		$last = '';
		foreach ( $this->dimTaint as $key => $taint ) {
			$ret .= "$first$kIndent    $key => " . $taint->toString( "$kIndent    " ) . "\n";
			$first = '';
			$last = $kIndent;
		}
		if ( $this->unknownDimsTaint ) {
			$ret .= "$first$kIndent    UNKNOWN => " . $this->unknownDimsTaint->toString( "$kIndent    " ) . "\n";
			$last = $kIndent;
		}
		$ret .= "$last}\n$indent}";
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness suitable for the debug annotation
	 *
	 * @return string
	 */
	public function toShortString(): string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$ret = "{Own: $flags";
		if ( $this->keysTaint ) {
			$ret .= '; Keys: ' . SecurityCheckPlugin::taintToString( $this->keysTaint );
		}
		$keyParts = [];
		if ( $this->dimTaint ) {
			foreach ( $this->dimTaint as $key => $taint ) {
				$keyParts[] = "$key => " . $taint->toShortString();
			}
		}
		if ( $this->unknownDimsTaint ) {
			$keyParts[] = 'UNKNOWN => ' . $this->unknownDimsTaint->toShortString();
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
