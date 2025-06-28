<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use Phan\Language\Element\FunctionInterface;

/**
 * Value object used to store caused-by lines.
 *
 * @note `clone` will not deep-clone instances of this class. That'd be more correct in theory, but it's
 * tremendously expensive for this class (+35s and +600MB for MW core).
 *
 * @todo Keep per-offset caused-by lines
 */
class CausedByLines {
	private const MAX_LINES_PER_ISSUE = 80;
	// XXX Hack: Enforce a hard limit, or things may explode
	private const LINES_HARD_LIMIT = 100;

	/**
	 * Note: the links are nullable for performance.
	 * @var array<array<Taintedness|string|MethodLinks|null>>
	 * @phan-var list<array{0:Taintedness,1:string,2:?MethodLinks}>
	 */
	private $lines = [];

	/**
	 * @param string[] $lines
	 * @param Taintedness $taintedness
	 * @param MethodLinks|null $links
	 * @note Taintedness and links are cloned as needed
	 */
	public function addLines( array $lines, Taintedness $taintedness, MethodLinks $links = null ): void {
		if ( !$this->lines ) {
			foreach ( $lines as $line ) {
				$this->lines[] = [ clone $taintedness, $line, $links ? clone $links : null ];
			}
			return;
		}

		foreach ( $lines as $line ) {
			if ( count( $this->lines ) >= self::LINES_HARD_LIMIT ) {
				break;
			}
			$idx = array_search( $line, array_column( $this->lines, 1 ), true );
			if ( $idx !== false ) {
				$this->lines[ $idx ][0]->mergeWith( $taintedness );
				if ( $links && !$this->lines[$idx][2] ) {
					$this->lines[$idx][2] = clone $links;
				} elseif ( $links && $links !== $this->lines[$idx][2] ) {
					$this->lines[$idx][2]->mergeWith( $links );
				}
			} else {
				$this->lines[] = [ clone $taintedness, $line, $links ? clone $links : null ];
			}
		}
	}

	/**
	 * Move any possibly preserved taintedness stored in the method links to the actual taintedness of this line,
	 * and use $links as the new links being preserved.
	 *
	 * @param Taintedness $taintedness
	 * @param MethodLinks $links
	 * @return self
	 */
	public function asPreservingTaintednessAndLinks( Taintedness $taintedness, MethodLinks $links ): self {
		$ret = new self;
		if ( !$this->lines ) {
			return $ret;
		}
		$curTaint = $taintedness->get();
		foreach ( $this->lines as [ $_, $eLine, $eLinks ] ) {
			$preservedFlags = $eLinks && ( $curTaint !== SecurityCheckPlugin::NO_TAINT )
				? $eLinks->filterPreservedFlags( $curTaint )
				: SecurityCheckPlugin::NO_TAINT;
			$ret->lines[] = [ new Taintedness( $preservedFlags ), $eLine, clone $links ];
		}
		return $ret;
	}

	/**
	 * @param Taintedness $taintedness
	 * @return self
	 */
	public function asIntersectedWithTaintedness( Taintedness $taintedness ): self {
		$ret = new self;
		if ( !$this->lines ) {
			return $ret;
		}
		$curTaint = $taintedness->get();
		foreach ( $this->lines as [ $eTaint, $eLine, $links ] ) {
			$newTaint = $curTaint !== SecurityCheckPlugin::NO_TAINT
				? $eTaint->withOnly( $curTaint )
				: new Taintedness( SecurityCheckPlugin::NO_TAINT );
			$ret->lines[] = [ $newTaint, $eLine, $links ];
		}
		return $ret;
	}

	/**
	 * @param FunctionInterface $func
	 * @param int $param
	 * @return self
	 */
	public function asFilteredForFuncAndParam( FunctionInterface $func, int $param ): self {
		$ret = new self;
		foreach ( $this->lines as $line ) {
			if ( $line[2] && $line[2]->hasDataForFuncAndParam( $func, $param ) ) {
				$ret->lines[] = $line;
			}
		}
		return $ret;
	}

	/**
	 * @return self
	 */
	public function getLinesForGenericReturn(): self {
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $_ ] ) {
			if ( !$lineTaint->isSafe() ) {
				// For generic lines, links don't matter
				$ret->lines[] = [ $lineTaint, $lineLine, null ];
			}
		}
		return $ret;
	}

	/**
	 * For every line in this object, check if the line has links for $func, and if so, add $taintedness to the
	 * line taintedness.
	 *
	 * @param Taintedness $taintedness
	 * @param FunctionInterface $func
	 * @param int $i Parameter index
	 * @return self
	 */
	public function withTaintAddedToMethodArgLinks( Taintedness $taintedness, FunctionInterface $func, int $i ): self {
		$ret = new self;
		foreach ( $this->lines as [ $lineTaint, $lineLine, $lineLinks ] ) {
			$newLinks = $lineLinks ? clone $lineLinks : null;
			if ( $lineLinks && $lineLinks->hasDataForFuncAndParam( $func, $i ) ) {
				$ret->lines[] = [ $lineTaint->asMergedWith( $taintedness ), $lineLine, $newLinks ];
			} else {
				$ret->lines[] = [ clone $lineTaint, $lineLine, $newLinks ];
			}
		}
		return $ret;
	}

	/**
	 * @note this isn't a merge operation like array_merge. What this method does is:
	 * 1 - if $other is a subset of $this, leave $this as-is;
	 * 2 - update taintedness values in $this if the *lines* (not taint values) in $other
	 *   are a subset of the lines in $this;
	 * 3 - if an upper set of $this *lines* is also a lower set of $other *lines*, remove that upper
	 *   set from $this and merge the rest with $other;
	 * 4 - array_merge otherwise;
	 *
	 * Step 2 is very important, because otherwise, caused-by lines can grow exponentially if
	 * even a single taintedness value in $this changes.
	 *
	 * @param self $other
	 */
	public function mergeWith( self $other ): void {
		if ( !$this->lines ) {
			$this->lines = $other->lines;
			return;
		}
		if ( !$other->lines || self::getArraySubsetIdx( $this->lines, $other->lines ) !== false ) {
			return;
		}

		$baseLines = array_column( $this->lines, 1 );
		$newLines = array_column( $other->lines, 1 );
		$subsIdx = self::getArraySubsetIdx( $baseLines, $newLines );
		if ( $subsIdx !== false ) {
			foreach ( $other->lines as $i => $cur ) {
				$this->lines[ $i + $subsIdx ][0]->mergeWith( $cur[0] );
				$curLinks = $cur[2];
				if ( $curLinks && !$this->lines[ $i + $subsIdx ][2] ) {
					$this->lines[$i + $subsIdx][2] = $curLinks;
				} elseif ( $curLinks && $curLinks !== $this->lines[ $i + $subsIdx ][2] ) {
					$this->lines[$i + $subsIdx][2]->mergeWith( $curLinks );
				}
			}
			return;
		}

		$ret = null;
		$baseLen = count( $this->lines );
		$newLen = count( $other->lines );
		// NOTE: array_shift is O(n), and O(n^2) over all iterations, because it reindexes the whole array.
		// So reverse the arrays, that is O(n) twice, and use array_pop which is O(1) (O(n) for all iterations)
		$remaining = array_reverse( $baseLines );
		$newRev = array_reverse( $newLines );
		// Assuming the lines as posets with the "natural" order used by PHP (that is, not the keys):
		// since we're working with reversed arrays, remaining lines should be an upper set of the reversed
		// new lines; which is to say, a lower set of the non-reversed new lines.
		$expectedIndex = $newLen - $baseLen;
		do {
			if ( $expectedIndex >= 0 && self::getArraySubsetIdx( $newRev, $remaining ) === $expectedIndex ) {
				$startIdx = $baseLen - $newLen + $expectedIndex;
				for ( $j = $startIdx; $j < $baseLen; $j++ ) {
					$this->lines[$j][0]->mergeWith( $other->lines[$j - $startIdx][0] );
					$secondLinks = $other->lines[$j - $startIdx][2];
					if ( $secondLinks && !$this->lines[$j][2] ) {
						$this->lines[$j][2] = $secondLinks;
					} elseif ( $secondLinks && $secondLinks !== $this->lines[$j][2] ) {
						$this->lines[$j][2]->mergeWith( $secondLinks );
					}
				}
				$ret = array_merge( $this->lines, array_slice( $other->lines, $newLen - $expectedIndex ) );
				break;
			}
			array_pop( $remaining );
			$expectedIndex++;
		} while ( $remaining );
		$ret ??= array_merge( $this->lines, $other->lines );

		$this->lines = array_slice( $ret, 0, self::LINES_HARD_LIMIT );
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
	 * Check whether $needle is subset of $haystack, regardless of the keys, and returns
	 * the starting index of the subset in the $haystack array. If the subset occurs multiple
	 * times, this will just find the first one.
	 *
	 * @param array[] $haystack
	 * @phan-param list<array{0:Taintedness,1:string,2:?MethodLinks}> $haystack
	 * @param array[] $needle
	 * @phan-param list<array{0:Taintedness,1:string,2:?MethodLinks}> $needle
	 * @return false|int False if not a subset, the starting index if it is.
	 * @note Use strict comparisons with the return value!
	 */
	private static function getArraySubsetIdx( array $haystack, array $needle ) {
		if ( !$needle || !$haystack ) {
			// For our needs, the empty array is not a subset of anything
			return false;
		}

		$needleLength = count( $needle );
		$haystackLength = count( $haystack );
		if ( $haystackLength < $needleLength ) {
			return false;
		}
		$curIdx = 0;
		foreach ( $haystack as $i => $el ) {
			if ( $el === $needle[ $curIdx ] ) {
				$curIdx++;
			} else {
				$curIdx = 0;
			}
			if ( $curIdx === $needleLength ) {
				return $i - ( $needleLength - 1 );
			}
		}
		return false;
	}

	/**
	 * Return a truncated, stringified representation of these lines to be used when reporting issues.
	 *
	 * @todo Perhaps this should include the first and last X lines, not the first 2X. However,
	 *   doing so would make phan emit a new issue for the same line whenever new caused-by
	 *   lines are added to the array.
	 *
	 * @param int $taintType Must only have normal flags, and no EXEC flags.
	 * @return string
	 */
	public function toStringForIssue( int $taintType ): string {
		$filteredLines = $this->getRelevantLinesForTaintedness( $taintType );
		if ( !$filteredLines ) {
			return '';
		}

		if ( count( $filteredLines ) <= self::MAX_LINES_PER_ISSUE ) {
			$linesPart = implode( '; ', $filteredLines );
		} else {
			$linesPart = implode( '; ', array_slice( $filteredLines, 0, self::MAX_LINES_PER_ISSUE ) ) . '; ...';
		}
		return ' (Caused by: ' . $linesPart . ')';
	}

	/**
	 * @param int $taintedness With only normal flags, and no EXEC flags.
	 * @return string[]
	 */
	private function getRelevantLinesForTaintedness( int $taintedness ): array {
		$ret = [];
		foreach ( $this->lines as [ $lineTaint, $lineText ] ) {
			if ( $lineTaint->has( $taintedness ) ) {
				$ret[] = $lineText;
			}
		}
		return $ret;
	}

	/**
	 * @return string[]
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function toLinesArray(): array {
		return array_column( $this->lines, 1 );
	}

	/**
	 * @return string
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function toDebugString(): string {
		$r = [];
		foreach ( $this->lines as [ $t, $line, $links ] ) {
			$r[] = "\t[\n\t\tT: " . $t->toShortString() . "\n\t\tL: " . $line . "\n\t\tLinks: " .
				( $links ?: 'none' ) . "\n\t]";
		}
		return "[\n" . implode( ",\n", $r ) . "\n]";
	}
}
