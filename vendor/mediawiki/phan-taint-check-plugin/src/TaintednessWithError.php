<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

/**
 * Object that encapsulates a Taintedness and a list of lines that caused it
 */
class TaintednessWithError {
	/** @var Taintedness */
	private $taintedness;
	/**
	 * @var CausedByLines
	 */
	private $error;

	/** @var MethodLinks */
	private $methodLinks;

	/**
	 * @param Taintedness $taintedness
	 * @param CausedByLines $error
	 * @param MethodLinks $methodLinks
	 */
	public function __construct( Taintedness $taintedness, CausedByLines $error, MethodLinks $methodLinks ) {
		$this->taintedness = $taintedness;
		$this->error = $error;
		$this->methodLinks = $methodLinks;
	}

	public static function newEmpty(): self {
		return new self( new Taintedness( SecurityCheckPlugin::NO_TAINT ), new CausedByLines(), new MethodLinks() );
	}

	/**
	 * @return Taintedness
	 */
	public function getTaintedness(): Taintedness {
		return $this->taintedness;
	}

	/**
	 * @return CausedByLines
	 */
	public function getError(): CausedByLines {
		return $this->error;
	}

	/**
	 * @return MethodLinks
	 */
	public function getMethodLinks(): MethodLinks {
		return $this->methodLinks;
	}

	/**
	 * @param TaintednessWithError $other
	 */
	public function mergeWith( self $other ): void {
		$this->taintedness->mergeWith( $other->taintedness );
		$this->error->mergeWith( $other->error );
		$this->methodLinks->mergeWith( $other->methodLinks );
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

	public function __clone() {
		$this->taintedness = clone $this->taintedness;
		$this->error = clone $this->error;
		$this->methodLinks = clone $this->methodLinks;
	}
}
