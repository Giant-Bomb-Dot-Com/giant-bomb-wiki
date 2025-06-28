<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

/**
 * Value object used to store taintedness of functions.
 * The $overall prop specifies what taint the function returns
 *   irrespective of its arguments.
 *
 *   For 'overall': only TAINT flags, what taint the output has
 *   For param keys: EXEC flags for what taints are unsafe here
 *                     TAINT flags for what taint gets passed through func.
 * As a special case, if the overall key has self::PRESERVE_TAINT
 * then any unspecified keys behave like they are self::YES_TAINT
 *
 * If func has no info for a parameter, the UnionType will be used to determine its taintedness.
 * The $overall taintedness must always be set.
 */
class FunctionTaintedness {
	/** @var Taintedness Overall taintedness of the func */
	private $overall;
	/** @var Taintedness[] EXEC taintedness for each param */
	private $paramSinkTaints = [];
	/** @var PreservedTaintedness[] Preserved taintedness for each param */
	private $paramPreserveTaints = [];
	/** @var int|null Index of a variadic parameter, if any */
	private $variadicParamIndex;
	/** @var Taintedness|null EXEC taintedness for a variadic parameter, if any */
	private $variadicParamSinkTaint;
	/** @var PreservedTaintedness|null Preserved taintedness for a variadic parameter, if any */
	private $variadicParamPreserveTaint;
	/** @var int Special overall flags */
	private $overallFlags = 0;
	/** @var int[] Special flags for parameters */
	private $paramFlags = [];
	/** @var int */
	private $variadicParamFlags = 0;

	/**
	 * @param Taintedness $overall
	 */
	public function __construct( Taintedness $overall ) {
		$this->overall = $overall;
	}

	/**
	 * @param Taintedness $val
	 */
	public function setOverall( Taintedness $val ): void {
		$this->overall = $val;
	}

	/**
	 * @param int $flags
	 */
	public function addOverallFlags( int $flags ): void {
		$this->overallFlags |= $flags;
	}

	/**
	 * Get the overall taint (NOT a clone)
	 *
	 * @return Taintedness
	 */
	public function getOverall(): Taintedness {
		return $this->overall;
	}

	/**
	 * @return bool
	 */
	public function canOverrideOverall(): bool {
		return ( $this->overallFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	/**
	 * Set the sink taint for a given param
	 *
	 * @param int $param
	 * @param Taintedness $taint
	 */
	public function setParamSinkTaint( int $param, Taintedness $taint ): void {
		assert( $param !== $this->variadicParamIndex );
		$this->paramSinkTaints[$param] = $taint;
	}

	/**
	 * Set the preserved taint for a given param
	 *
	 * @param int $param
	 * @param PreservedTaintedness $taint
	 */
	public function setParamPreservedTaint( int $param, PreservedTaintedness $taint ): void {
		assert( $param !== $this->variadicParamIndex );
		$this->paramPreserveTaints[$param] = $taint;
	}

	/**
	 * @param int $param
	 * @param int $flags
	 */
	public function addParamFlags( int $param, int $flags ): void {
		$this->paramFlags[$param] = ( $this->paramFlags[$param] ?? 0 ) | $flags;
	}

	/**
	 * @param int $index
	 * @param Taintedness $taint
	 */
	public function setVariadicParamSinkTaint( int $index, Taintedness $taint ): void {
		assert( !isset( $this->paramPreserveTaints[$index] ) && !isset( $this->paramSinkTaints[$index] ) );
		$this->variadicParamIndex = $index;
		$this->variadicParamSinkTaint = $taint;
	}

	/**
	 * @param int $index
	 * @param PreservedTaintedness $taint
	 */
	public function setVariadicParamPreservedTaint( int $index, PreservedTaintedness $taint ): void {
		assert( !isset( $this->paramPreserveTaints[$index] ) && !isset( $this->paramSinkTaints[$index] ) );
		$this->variadicParamIndex = $index;
		$this->variadicParamPreserveTaint = $taint;
	}

	/**
	 * @param int $flags
	 */
	public function addVariadicParamFlags( int $flags ): void {
		$this->variadicParamFlags |= $flags;
	}

	/**
	 * Get the sink taintedness of the given param (NOT a clone), and NO_TAINT if not set.
	 *
	 * @param int $param
	 * @return Taintedness
	 */
	public function getParamSinkTaint( int $param ): Taintedness {
		if ( isset( $this->paramSinkTaints[$param] ) ) {
			return $this->paramSinkTaints[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamSinkTaint
		) {
			return $this->variadicParamSinkTaint;
		}
		return Taintedness::newSafe();
	}

	/**
	 * Get the preserved taintedness of the given param (NOT a clone), and NO_TAINT if not set.
	 *
	 * @param int $param
	 * @return PreservedTaintedness
	 */
	public function getParamPreservedTaint( int $param ): PreservedTaintedness {
		if ( isset( $this->paramPreserveTaints[$param] ) ) {
			return $this->paramPreserveTaints[$param];
		}
		if (
			$this->variadicParamIndex !== null && $param >= $this->variadicParamIndex &&
			$this->variadicParamPreserveTaint
		) {
			return $this->variadicParamPreserveTaint;
		}
		return PreservedTaintedness::newEmpty();
	}

	/**
	 * @param int $param
	 * @return int
	 */
	public function getParamFlags( int $param ): int {
		if ( isset( $this->paramFlags[$param] ) ) {
			return $this->paramFlags[$param];
		}
		if ( $this->variadicParamIndex !== null && $param >= $this->variadicParamIndex ) {
			return $this->variadicParamFlags;
		}
		return 0;
	}

	/**
	 * @param int $param
	 * @return bool
	 */
	public function canOverrideNonVariadicParam( int $param ): bool {
		return ( ( $this->paramFlags[$param] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	/**
	 * @return Taintedness|null
	 */
	public function getVariadicParamSinkTaint(): ?Taintedness {
		return $this->variadicParamSinkTaint;
	}

	/**
	 * @return PreservedTaintedness|null
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function getVariadicParamPreservedTaint(): ?PreservedTaintedness {
		return $this->variadicParamPreserveTaint;
	}

	/**
	 * @return int|null
	 */
	public function getVariadicParamIndex(): ?int {
		return $this->variadicParamIndex;
	}

	/**
	 * @return bool
	 */
	public function canOverrideVariadicParam(): bool {
		return ( $this->variadicParamFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0;
	}

	/**
	 * Get the *keys* of the params for which we have sink data, excluding variadic parameters
	 *
	 * @return int[]
	 */
	public function getSinkParamKeysNoVariadic(): array {
		return array_keys( $this->paramSinkTaints );
	}

	/**
	 * Get the *keys* of the params for which we have preserve data, excluding variadic parameters
	 *
	 * @return int[]
	 */
	public function getPreserveParamKeysNoVariadic(): array {
		return array_keys( $this->paramPreserveTaints );
	}

	/**
	 * Check whether we have preserve taint data for the given param
	 *
	 * @param int $param
	 * @return bool
	 */
	public function hasParamPreserve( int $param ): bool {
		if ( isset( $this->paramPreserveTaints[$param] ) ) {
			return true;
		}
		if ( $this->variadicParamIndex !== null && $param >= $this->variadicParamIndex ) {
			return (bool)$this->variadicParamPreserveTaint;
		}
		return false;
	}

	/**
	 * Merge this object with another. This respects NO_OVERRIDE, since it doesn't touch any element
	 * where it's set. If the overall taint has UNKNOWN, it's cleared if we're setting it now.
	 * @param self $other
	 */
	public function mergeWith( self $other ): void {
		foreach ( $other->paramSinkTaints as $index => $baseT ) {
			if ( ( ( $this->paramFlags[$index] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
				if ( isset( $this->paramSinkTaints[$index] ) ) {
					$this->paramSinkTaints[$index]->mergeWith( $baseT );
				} else {
					$this->paramSinkTaints[$index] = $baseT;
				}
				$this->paramFlags[$index] = ( $this->paramFlags[$index] ?? 0 ) | ( $other->paramFlags[$index] ?? 0 );
			}
		}
		foreach ( $other->paramPreserveTaints as $index => $baseT ) {
			if ( ( ( $this->paramFlags[$index] ?? 0 ) & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
				if ( isset( $this->paramPreserveTaints[$index] ) ) {
					$this->paramPreserveTaints[$index]->mergeWith( $baseT );
				} else {
					$this->paramPreserveTaints[$index] = $baseT;
				}
				$this->paramFlags[$index] = ( $this->paramFlags[$index] ?? 0 ) | ( $other->paramFlags[$index] ?? 0 );
			}
		}

		if ( ( $this->variadicParamFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
			$variadicIndex = $other->variadicParamIndex;
			if ( $variadicIndex !== null ) {
				$this->variadicParamIndex = $variadicIndex;
				$sinkVariadic = $other->variadicParamSinkTaint;
				if ( $sinkVariadic ) {
					if ( $this->variadicParamSinkTaint ) {
						$this->variadicParamSinkTaint->mergeWith( $sinkVariadic );
					} else {
						$this->variadicParamSinkTaint = $sinkVariadic;
					}
				}
				$presVariadic = $other->variadicParamPreserveTaint;
				if ( $presVariadic ) {
					if ( $this->variadicParamPreserveTaint ) {
						$this->variadicParamPreserveTaint->mergeWith( $presVariadic );
					} else {
						$this->variadicParamPreserveTaint = $presVariadic;
					}
				}
				$this->variadicParamFlags |= $other->variadicParamFlags;
			}
		}

		if ( ( $this->overallFlags & SecurityCheckPlugin::NO_OVERRIDE ) === 0 ) {
			// Remove UNKNOWN, which could be added e.g. when building func taint from the return type.
			$this->overall->remove( SecurityCheckPlugin::UNKNOWN_TAINT );
			$this->overall->mergeWith( $other->overall );
			$this->overallFlags |= $other->overallFlags;
		}
	}

	/**
	 * @param self $other
	 * @return self
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	/**
	 * Make sure to clone properties when cloning the instance
	 */
	public function __clone() {
		$this->overall = clone $this->overall;
		foreach ( $this->paramSinkTaints as $k => $e ) {
			$this->paramSinkTaints[$k] = clone $e;
		}
		foreach ( $this->paramPreserveTaints as $k => $t ) {
			$this->paramPreserveTaints[$k] = clone $t;
		}
		if ( $this->variadicParamSinkTaint ) {
			$this->variadicParamSinkTaint = clone $this->variadicParamSinkTaint;
		}
		if ( $this->variadicParamPreserveTaint ) {
			$this->variadicParamPreserveTaint = clone $this->variadicParamPreserveTaint;
		}
	}

	/**
	 * @return string
	 */
	public function toString(): string {
		$str = "[\n\toverall: " . $this->overall->toShortString() .
			self::flagsToString( $this->overallFlags ) . ",\n";
		$parKeys = array_unique( array_merge(
			array_keys( $this->paramSinkTaints ),
			array_keys( $this->paramPreserveTaints )
		) );
		foreach ( $parKeys as $par ) {
			$str .= "\t$par: {";
			if ( isset( $this->paramSinkTaints[$par] ) ) {
				$str .= "Sink: " . $this->paramSinkTaints[$par]->toShortString() . ', ';
			}
			if ( isset( $this->paramPreserveTaints[$par] ) ) {
				$str .= "Preserve: " . $this->paramPreserveTaints[$par]->toShortString();
			}
			$str .= '} ' . self::flagsToString( $this->paramFlags[$par] ?? 0 ) . ",\n";
		}
		if ( $this->variadicParamIndex !== null ) {
			$str .= "\t...{$this->variadicParamIndex}: {";
			if ( $this->variadicParamSinkTaint ) {
				 $str .= "Sink: " . $this->variadicParamSinkTaint->toShortString() . ', ';
			}
			if ( $this->variadicParamPreserveTaint ) {
				$str .= "Preserve: " . $this->variadicParamPreserveTaint->toShortString();
			}
			$str .= '} ' . self::flagsToString( $this->variadicParamFlags ) . "\n";
		}
		return "$str]";
	}

	/**
	 * @param int $flags
	 * @return string
	 */
	private static function flagsToString( int $flags ): string {
		$bits = [];
		if ( $flags & SecurityCheckPlugin::NO_OVERRIDE ) {
			$bits[] = 'no override';
		}
		if ( $flags & SecurityCheckPlugin::ARRAY_OK ) {
			$bits[] = 'array ok';
		}
		return $bits ? ' (' . implode( ', ', $bits ) . ')' : '';
	}

	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->toString();
	}
}
