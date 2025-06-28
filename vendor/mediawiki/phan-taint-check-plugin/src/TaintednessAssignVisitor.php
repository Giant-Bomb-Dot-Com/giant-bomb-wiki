<?php

namespace SecurityCheckPlugin;

use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\UnanalyzableException;
use Phan\Language\Context;
use Phan\Language\Element\GlobalVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\TypedElementInterface;
use Phan\PluginV3\PluginAwareBaseAnalysisVisitor;

/**
 * @see \Phan\Analysis\AssignmentVisitor
 */
class TaintednessAssignVisitor extends PluginAwareBaseAnalysisVisitor {
	use TaintednessBaseVisitor;

	/** @var Taintedness */
	private $rightTaint;

	/** @var Taintedness */
	private $errorTaint;

	/** @var MethodLinks */
	private $errorLinks;

	/** @var CausedByLines */
	private $rightError;

	/** @var MethodLinks */
	private $rightLinks;

	/** @var bool|null */
	private $rhsIsArray;

	/** @var Closure|null */
	private $rhsIsArrayGetter;

	/** @var int */
	private $dimDepth;

	/**
	 * @inheritDoc
	 * @param Taintedness $rightTaint
	 * @param CausedByLines $rightLines
	 * @param MethodLinks $rightLinks
	 * @param Taintedness $errorTaint
	 * @param MethodLinks $errorLinks
	 * @param Closure|bool $rhsIsArrayOrGetter
	 * @phan-param Closure():bool|bool $rhsIsArrayOrGetter
	 * @param int $depth
	 */
	public function __construct(
		CodeBase $code_base,
		Context $context,
		Taintedness $rightTaint,
		CausedByLines $rightLines,
		MethodLinks $rightLinks,
		Taintedness $errorTaint,
		MethodLinks $errorLinks,
		$rhsIsArrayOrGetter,
		int $depth = 0
	) {
		parent::__construct( $code_base, $context );
		$this->rightTaint = $rightTaint;
		$this->rightError = $rightLines;
		$this->rightLinks = $rightLinks;
		$this->errorTaint = $errorTaint;
		$this->errorLinks = $errorLinks;
		if ( is_callable( $rhsIsArrayOrGetter ) ) {
			$this->rhsIsArrayGetter = $rhsIsArrayOrGetter;
		} else {
			$this->rhsIsArray = $rhsIsArrayOrGetter;
		}
		$this->dimDepth = $depth;
	}

	private function isRHSArray(): bool {
		if ( $this->rhsIsArray !== null ) {
			return $this->rhsIsArray;
		}
		$this->rhsIsArray = ( $this->rhsIsArrayGetter )();
		return $this->rhsIsArray;
	}

	/**
	 * @param Node $node
	 */
	public function visitArray( Node $node ): void {
		$numKey = 0;
		foreach ( $node->children as $child ) {
			if ( $child === null ) {
				$numKey++;
				continue;
			}
			if ( !$child instanceof Node || $child->kind !== \ast\AST_ARRAY_ELEM ) {
				// Syntax error.
				return;
			}
			$key = $child->children['key'] !== null ? $this->resolveOffset( $child->children['key'] ) : $numKey++;
			$value = $child->children['value'];
			if ( !$value instanceof Node ) {
				// Syntax error, don't crash, and bail out immediately.
				return;
			}
			$childVisitor = new self(
				$this->code_base,
				$this->context,
				$this->rightTaint->getTaintednessForOffsetOrWhole( $key ),
				$this->rightError,
				$this->rightLinks,
				$this->errorTaint->getTaintednessForOffsetOrWhole( $key ),
				$this->errorLinks,
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$this->rhsIsArray ?? $this->rhsIsArrayGetter,
				$this->dimDepth
			);
			$childVisitor( $value );
		}
	}

	/**
	 * @param Node $node
	 */
	public function visitVar( Node $node ): void {
		try {
			$var = $this->getCtxN( $node )->getVariable();
		} catch ( NodeException | IssueException $_ ) {
			return;
		}
		$this->doAssignmentSingleElement( $var );
	}

	/**
	 * @param Node $node
	 */
	public function visitProp( Node $node ): void {
		try {
			$prop = $this->getCtxN( $node )->getProperty( false );
		} catch ( NodeException | IssueException | UnanalyzableException $_ ) {
			return;
		}
		$this->doAssignmentSingleElement( $prop );
	}

	/**
	 * @param Node $node
	 */
	public function visitStaticProp( Node $node ): void {
		try {
			$prop = $this->getCtxN( $node )->getProperty( true );
		} catch ( NodeException | IssueException | UnanalyzableException $_ ) {
			return;
		}
		$this->doAssignmentSingleElement( $prop );
	}

	/**
	 * If we're assigning an SQL tainted value as an array key
	 * or as the value of a numeric key, then set NUMKEY taint.
	 *
	 * @param Node $dimLHS
	 */
	private function maybeAddNumkeyOnAssignmentLHS( Node $dimLHS ): void {
		if ( $this->rightTaint->has( SecurityCheckPlugin::SQL_NUMKEY_TAINT ) ) {
			// Already there, no need to add it again.
			return;
		}

		$dim = $dimLHS->children['dim'];
		if (
			$this->rightTaint->has( SecurityCheckPlugin::SQL_TAINT )
			&& ( $dim === null || $this->nodeCanBeIntKey( $dim ) )
			&& !$this->isRHSArray()
		) {
			$this->rightTaint->add( SecurityCheckPlugin::SQL_NUMKEY_TAINT );
			$this->errorTaint->add( SecurityCheckPlugin::SQL_NUMKEY_TAINT );
		}
	}

	/**
	 * @param Node $node
	 */
	public function visitDim( Node $node ): void {
		if ( !$node->children['expr'] instanceof Node ) {
			// Invalid syntax.
			return;
		}
		$dimNode = $node->children['dim'];
		if ( $dimNode === null ) {
			$curOff = null;
		} else {
			$curOff = $this->resolveOffset( $dimNode );
		}
		$this->dimDepth++;
		$dimTaintWithErr = $this->getTaintedness( $dimNode );
		$dimTaintInt = $dimTaintWithErr->getTaintedness()->get();
		$this->rightTaint = $this->rightTaint->asMaybeMovedAtOffset( $curOff, $dimTaintInt );
		$dimLinks = $dimTaintWithErr->getMethodLinks()->getLinksCollapsing();
		$this->rightLinks = $this->rightLinks->asMaybeMovedAtOffset( $curOff, $dimLinks );
		$this->errorTaint->addKeysTaintedness( $dimTaintInt );
		$this->maybeAddNumkeyOnAssignmentLHS( $node );
		$this( $node->children['expr'] );
	}

	/**
	 * @param TypedElementInterface $variableObj
	 */
	private function doAssignmentSingleElement(
		TypedElementInterface $variableObj
	): void {
		$globalVarObj = $variableObj instanceof GlobalVariable ? $variableObj->getElement() : null;

		// Make sure assigning to $this->bar doesn't kill the whole prop taint.
		// Note: If there is a local variable that is a reference to another non-local variable, this will not
		// affect the non-local one (Pass by reference arguments are handled separately and work as expected).
		// TODO Should we also check for normal Variables in the global scope? See test setafterexec
		$override = !( $variableObj instanceof Property ) && !$globalVarObj;

		$overrideTaint = $override;
		if ( $this->dimDepth > 0 ) {
			$curTaint = self::getTaintednessRaw( $variableObj );
			if ( $curTaint ) {
				$newTaint = $override
					? $curTaint->asMergedForAssignment( $this->rightTaint, $this->dimDepth )
					: $curTaint->asMergedWith( $this->rightTaint );
			} else {
				$newTaint = $this->rightTaint;
			}
			$overrideTaint = true;
		} else {
			$newTaint = $this->rightTaint;
		}
		$this->setTaintedness( $variableObj, $newTaint, $overrideTaint );

		if ( $globalVarObj ) {
			// Merge the taint on the "true" global object, too
			if ( $this->dimDepth > 0 ) {
				$curGlobalTaint = self::getTaintednessRaw( $globalVarObj );
				if ( $curGlobalTaint ) {
					$newGlobalTaint = clone $curGlobalTaint;
					$newGlobalTaint->mergeWith( $this->rightTaint );
				} else {
					$newGlobalTaint = $this->rightTaint;
				}
				$overrideGlobalTaint = true;
			} else {
				$newGlobalTaint = $this->rightTaint;
				$overrideGlobalTaint = false;
			}
			$this->setTaintedness( $globalVarObj, $newGlobalTaint, $overrideGlobalTaint );
		}

		if ( $this->dimDepth > 0 ) {
			$curLinks = self::getMethodLinksCloneOrEmpty( $variableObj );
			$newLinks = $override
				? $curLinks->asMergedForAssignment( $this->rightLinks, $this->dimDepth )
				: $curLinks->asMergedWith( $this->rightLinks );
			$overrideLinks = true;
		} else {
			$newLinks = $this->rightLinks;
			$overrideLinks = $override;
		}
		$this->mergeTaintDependencies( $variableObj, $newLinks, $overrideLinks );
		if ( $globalVarObj ) {
			// Merge dependencies on the global copy as well
			if ( $this->dimDepth > 0 ) {
				$curGlobalLinks = self::getMethodLinksCloneOrEmpty( $globalVarObj );
				$newGlobalLinks = $curGlobalLinks->asMergedWith( $this->rightLinks );
				$overrideGlobalLinks = true;
			} else {
				$newGlobalLinks = $this->rightLinks;
				$overrideGlobalLinks = false;
			}
			$this->mergeTaintDependencies( $globalVarObj, $newGlobalLinks, $overrideGlobalLinks );
		}

		if ( $this->dimDepth > 0 ) {
			$curError = self::getCausedByRaw( $variableObj );
			$newError = $curError ? clone $curError : new CausedByLines();
			$newError->mergeWith( $this->rightError );
			$overrideError = true;
		} else {
			$newError = $this->rightError;
			$overrideError = $override;
		}
		if ( $overrideError ) {
			self::clearTaintError( $variableObj );
		}
		$this->addTaintError( $variableObj, $this->errorTaint, $this->errorLinks );
		$this->mergeTaintError( $variableObj, $newError );
		if ( $globalVarObj ) {
			$this->addTaintError( $globalVarObj, $this->errorTaint, $this->errorLinks );
			$this->mergeTaintError( $globalVarObj, $newError );
		}
	}
}
