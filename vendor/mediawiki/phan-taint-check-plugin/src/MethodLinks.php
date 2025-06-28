<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\FunctionInterface;

/**
 * Value object that represents method links.
 * @todo We might store links inside Taintedness, but the memory usage might skyrocket
 */
class MethodLinks {
	/** @var LinksSet */
	private $links;

	/** @var self[] */
	private $dimLinks = [];

	/** @var self|null */
	private $unknownDimLinks;

	/** @var LinksSet|null */
	private $keysLinks;

	/**
	 * @param LinksSet|null $links
	 */
	public function __construct( LinksSet $links = null ) {
		$this->links = $links ?? new LinksSet();
	}

	/**
	 * @return self
	 */
	public static function newEmpty(): self {
		return new self( new LinksSet );
	}

	/**
	 * @note This returns a clone
	 * @param mixed $dim
	 * @return self
	 */
	public function getForDim( $dim ): self {
		if ( !is_scalar( $dim ) ) {
			return $this->asValueFirstLevel()->withAddedOffset( $dim );
		}
		if ( isset( $this->dimLinks[$dim] ) ) {
			$ret = clone $this->dimLinks[$dim];
			$ret->mergeWith( $this->unknownDimLinks ?? self::newEmpty() );
			$ret->links->mergeWith( $this->links );
			return $ret->withAddedOffset( $dim );
		}
		$ret = $this->unknownDimLinks ? clone $this->unknownDimLinks : self::newEmpty();
		$ret->links->mergeWith( $this->links );
		return $ret->withAddedOffset( $dim );
	}

	/**
	 * @return self
	 */
	public function asValueFirstLevel(): self {
		$ret = new self( clone $this->links );
		$ret->mergeWith( $this->unknownDimLinks ?? self::newEmpty() );
		foreach ( $this->dimLinks as $links ) {
			$ret->mergeWith( $links );
		}
		return $ret;
	}

	/**
	 * @return self
	 */
	public function asKeyForForeach(): self {
		if ( $this->keysLinks ) {
			$links = $this->keysLinks->asMergedWith( $this->links );
		} else {
			$links = $this->links;
		}
		return new self( $links->asAllMovedToKeys() );
	}

	/**
	 * @param mixed $dim
	 * @param MethodLinks $links
	 */
	public function setAtDim( $dim, self $links ): void {
		if ( is_scalar( $dim ) ) {
			$this->dimLinks[$dim] = $links;
		} else {
			$this->unknownDimLinks ??= self::newEmpty();
			$this->unknownDimLinks->mergeWith( $links );
		}
	}

	/**
	 * @param LinksSet $links
	 */
	public function addKeysLinks( LinksSet $links ): void {
		if ( !$this->keysLinks ) {
			$this->keysLinks = $links;
		} else {
			$this->keysLinks->mergeWith( $links );
		}
	}

	/**
	 * @return self
	 */
	public function asCollapsed(): self {
		$ret = new self( $this->links );
		foreach ( $this->dimLinks as $links ) {
			$ret->mergeWith( $links->asCollapsed() );
		}
		if ( $this->unknownDimLinks ) {
			$ret->mergeWith( $this->unknownDimLinks->asCollapsed() );
		}
		return $ret;
	}

	/**
	 * Merge this object with $other, recursively and without creating a copy.
	 *
	 * @param self $other
	 */
	public function mergeWith( self $other ): void {
		$this->links->mergeWith( $other->links );
		foreach ( $other->dimLinks as $key => $links ) {
			if ( isset( $this->dimLinks[$key] ) ) {
				$this->dimLinks[$key]->mergeWith( $links );
			} else {
				$this->dimLinks[$key] = $links;
			}
		}
		if ( $other->unknownDimLinks && !$this->unknownDimLinks ) {
			$this->unknownDimLinks = $other->unknownDimLinks;
		} elseif ( $other->unknownDimLinks ) {
			$this->unknownDimLinks->mergeWith( $other->unknownDimLinks );
		}
		if ( $other->keysLinks && !$this->keysLinks ) {
			$this->keysLinks = $other->keysLinks;
		} elseif ( $other->keysLinks ) {
			$this->keysLinks->mergeWith( $other->keysLinks );
		}
	}

	/**
	 * Merge this object with $other, recursively, creating a copy.
	 *
	 * @param self $other
	 * @return self
	 */
	public function asMergedWith( self $other ): self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	/**
	 * @param Node|mixed $offset
	 * @return self
	 */
	public function withAddedOffset( $offset ): self {
		$ret = clone $this;
		foreach ( $ret->links as $func ) {
			$ret->links[$func]->pushOffsetToAll( $offset );
		}
		return $ret;
	}

	/**
	 * Create a new object with $this at the given $offset (if scalar) or as unknown object.
	 *
	 * @param Node|string|int|bool|float|null $offset
	 * @param LinksSet|null $keyLinks
	 * @return self Always a copy
	 */
	public function asMaybeMovedAtOffset( $offset, LinksSet $keyLinks = null ): self {
		$ret = new self;
		if ( $offset instanceof Node || $offset === null ) {
			$ret->unknownDimLinks = clone $this;
		} else {
			$ret->dimLinks[$offset] = clone $this;
		}
		$ret->keysLinks = $keyLinks;
		return $ret;
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
		$ret->links->mergeWith( $other->links );
		if ( !$ret->keysLinks ) {
			$ret->keysLinks = $other->keysLinks;
		} elseif ( $other->keysLinks ) {
			$ret->keysLinks->mergeWith( $other->keysLinks );
		}
		if ( !$ret->unknownDimLinks ) {
			$ret->unknownDimLinks = $other->unknownDimLinks;
		} elseif ( $other->unknownDimLinks ) {
			$ret->unknownDimLinks->mergeWith( $other->unknownDimLinks );
		}
		foreach ( $other->dimLinks as $k => $v ) {
			$ret->dimLinks[$k] = isset( $ret->dimLinks[$k] )
				? $ret->dimLinks[$k]->asMergedForAssignment( $v, $depth - 1 )
				: $v;
		}
		$ret->normalize();
		return $ret;
	}

	/**
	 * Remove offset links which are already present in the "main" links. This is done for performance
	 * (see test backpropoffsets-blowup).
	 *
	 * @todo Improve (e.g. recurse)
	 * @todo Might happen sometime earlier
	 */
	private function normalize(): void {
		if ( !count( $this->links ) ) {
			return;
		}
		foreach ( $this->dimLinks as $k => $links ) {
			foreach ( $links->links as $func ) {
				if ( $this->links->contains( $func ) ) {
					$dimParams = array_keys( $links->links[$func]->getParams() );
					$thisParams = array_keys( $this->links[$func]->getParams() );
					$keepParams = array_diff( $dimParams, $thisParams );
					if ( !$keepParams ) {
						unset( $links->links[$func] );
					} else {
						$links->links[$func]->keepOnlyParams( $keepParams );
					}
				}
			}
			if ( $links->isEmpty() ) {
				unset( $this->dimLinks[$k] );
			}
		}
		if ( $this->unknownDimLinks ) {
			foreach ( $this->unknownDimLinks->links as $func ) {
				if ( $this->links->contains( $func ) ) {
					$dimParams = array_keys( $this->unknownDimLinks->links[$func]->getParams() );
					$thisParams = array_keys( $this->links[$func]->getParams() );
					$keepParams = array_diff( $dimParams, $thisParams );
					if ( !$keepParams ) {
						unset( $this->unknownDimLinks->links[$func] );
					} else {
						$this->unknownDimLinks->links[$func]->keepOnlyParams( $keepParams );
					}
				}
			}
			if ( $this->unknownDimLinks->isEmpty() ) {
				$this->unknownDimLinks = null;
			}
		}
	}

	/**
	 * Make sure to clone member variables, too.
	 */
	public function __clone() {
		$this->links = clone $this->links;
		foreach ( $this->dimLinks as $k => $links ) {
			$this->dimLinks[$k] = clone $links;
		}
		if ( $this->unknownDimLinks ) {
			$this->unknownDimLinks = clone $this->unknownDimLinks;
		}
		if ( $this->keysLinks ) {
			$this->keysLinks = clone $this->keysLinks;
		}
	}

	/**
	 * Returns all the links stored in this object as a single LinkSet object, destroying the shape. This should only
	 * be used when the shape is not relevant.
	 *
	 * @return LinksSet
	 */
	public function getLinksCollapsing(): LinksSet {
		$ret = clone $this->links;
		foreach ( $this->dimLinks as $link ) {
			$ret->mergeWith( $link->getLinksCollapsing() );
		}
		if ( $this->unknownDimLinks ) {
			$ret->mergeWith( $this->unknownDimLinks->getLinksCollapsing() );
		}
		if ( $this->keysLinks ) {
			$ret->mergeWith( $this->keysLinks );
		}
		return $ret;
	}

	/**
	 * @return array[]
	 * @phan-return array<array{0:FunctionInterface,1:int}>
	 */
	public function getMethodAndParamTuples(): array {
		$ret = [];
		foreach ( $this->links as $func ) {
			$info = $this->links[$func];
			foreach ( $info->getParams() as $i => $_ ) {
				$ret[] = [ $func, $i ];
			}
		}
		foreach ( $this->dimLinks as $link ) {
			$ret = array_merge( $ret, $link->getMethodAndParamTuples() );
		}
		if ( $this->unknownDimLinks ) {
			$ret = array_merge( $ret, $this->unknownDimLinks->getMethodAndParamTuples() );
		}
		foreach ( $this->keysLinks ?? [] as $func ) {
			$info = $this->keysLinks[$func];
			foreach ( $info->getParams() as $i => $_ ) {
				$ret[] = [ $func, $i ];
			}
		}
		return array_unique( $ret, SORT_REGULAR );
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool {
		if ( count( $this->links ) ) {
			return false;
		}
		foreach ( $this->dimLinks as $links ) {
			if ( !$links->isEmpty() ) {
				return false;
			}
		}
		if ( $this->unknownDimLinks && !$this->unknownDimLinks->isEmpty() ) {
			return false;
		}
		if ( $this->keysLinks && count( $this->keysLinks ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param FunctionInterface $func
	 * @param int $i
	 * @return bool
	 */
	public function hasDataForFuncAndParam( FunctionInterface $func, int $i ): bool {
		if ( $this->links->contains( $func ) && $this->links[$func]->hasParam( $i ) ) {
			return true;
		}
		foreach ( $this->dimLinks as $dimLinks ) {
			if ( $dimLinks->hasDataForFuncAndParam( $func, $i ) ) {
				return true;
			}
		}
		if ( $this->unknownDimLinks && $this->unknownDimLinks->hasDataForFuncAndParam( $func, $i ) ) {
			return true;
		}
		if ( $this->keysLinks && $this->keysLinks->contains( $func ) && $this->keysLinks[$func]->hasParam( $i ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param FunctionInterface $func
	 * @param int $i
	 */
	public function initializeParamForFunc( FunctionInterface $func, int $i ): void {
		if ( $this->links->contains( $func ) ) {
			$this->links[$func]->addParam( $i );
		} else {
			$this->links[$func] = SingleMethodLinks::newWithParam( $i );
		}
	}

	/**
	 * Given some taint flags, return their intersection with the flags that can be preserved by this object
	 * @param int $taint
	 * @return int
	 */
	public function filterPreservedFlags( int $taint ): int {
		return $taint & $this->getAllPreservedFlags();
	}

	/**
	 * @return int
	 */
	private function getAllPreservedFlags(): int {
		$ret = SecurityCheckPlugin::NO_TAINT;
		foreach ( $this->links as $func ) {
			$ret |= $this->links[$func]->getAllPreservedFlags();
		}
		foreach ( $this->dimLinks as $dimLinks ) {
			$ret |= $dimLinks->getAllPreservedFlags();
		}
		if ( $this->unknownDimLinks ) {
			$ret |= $this->unknownDimLinks->getAllPreservedFlags();
		}
		foreach ( $this->keysLinks ?? [] as $func ) {
			$ret |= $this->keysLinks[$func]->getAllPreservedFlags();
		}
		return $ret;
	}

	/**
	 * @param FunctionInterface $func
	 * @param int $param
	 * @return PreservedTaintedness
	 */
	public function asPreservedTaintednessForFuncParam( FunctionInterface $func, int $param ): PreservedTaintedness {
		$ret = null;
		if ( $this->links->contains( $func ) ) {
			$ownInfo = $this->links[$func];
			if ( $ownInfo->hasParam( $param ) ) {
				$ret = new PreservedTaintedness( $ownInfo->getParamOffsets( $param ) );
			}
		}
		if ( !$ret ) {
			$ret = new PreservedTaintedness( ParamLinksOffsets::newEmpty() );
		}
		foreach ( $this->dimLinks as $dim => $dimLinks ) {
			$ret->setOffsetTaintedness( $dim, $dimLinks->asPreservedTaintednessForFuncParam( $func, $param ) );
		}
		if ( $this->unknownDimLinks ) {
			$ret->setOffsetTaintedness(
				null,
				$this->unknownDimLinks->asPreservedTaintednessForFuncParam( $func, $param )
			);
		}
		if ( $this->keysLinks && $this->keysLinks->contains( $func ) ) {
			$keyInfo = $this->keysLinks[$func];
			if ( $keyInfo->hasParam( $param ) ) {
				$ret->setKeysOffsets( $keyInfo->getParamOffsets( $param ) );
			}
		}
		return $ret;
	}

	/**
	 * @param FunctionInterface $func
	 * @param int $param
	 * @return self
	 */
	public function asFilteredForFuncAndParam( FunctionInterface $func, int $param ): self {
		$retLinks = new LinksSet();
		if ( $this->links->contains( $func ) ) {
			$retLinks->attach( $func, $this->links[$func] );
		}
		$ret = new self( $retLinks );
		foreach ( $this->dimLinks as $dim => $dimLinks ) {
			$ret->setAtDim( $dim, $dimLinks->asFilteredForFuncAndParam( $func, $param ) );
		}
		if ( $this->unknownDimLinks ) {
			$ret->setAtDim(
				null,
				$this->unknownDimLinks->asFilteredForFuncAndParam( $func, $param )
			);
		}
		if ( $this->keysLinks && $this->keysLinks->contains( $func ) ) {
			$ret->keysLinks = new LinksSet();
			$ret->keysLinks->attach( $func, $this->keysLinks[$func] );
		}
		return $ret;
	}

	/**
	 * @param string $indent
	 * @return string
	 */
	public function toString( string $indent = '' ): string {
		$elementsIndent = $indent . "\t";
		$ret = "{\n$elementsIndent" . 'OWN: ' . $this->links->__toString() . ',';
		if ( $this->keysLinks ) {
			$ret .= "\n{$elementsIndent}KEYS: " . $this->keysLinks->__toString() . ',';
		}
		if ( $this->dimLinks || $this->unknownDimLinks ) {
			$ret .= "\n{$elementsIndent}CHILDREN: {";
			$childrenIndent = $elementsIndent . "\t";
			foreach ( $this->dimLinks as $key => $links ) {
				$ret .= "\n$childrenIndent$key: " . $links->toString( $childrenIndent ) . ',';
			}
			if ( $this->unknownDimLinks ) {
				$ret .= "\n$childrenIndent(UNKNOWN): " . $this->unknownDimLinks->toString( $childrenIndent );
			}
			$ret .= "\n$elementsIndent}";
		}
		return $ret . "\n$indent}";
	}

	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->toString();
	}
}
