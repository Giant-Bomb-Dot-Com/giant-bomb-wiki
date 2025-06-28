<?php

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\PluginV3\BeforeLoopBodyAnalysisVisitor;

class TaintednessLoopVisitor extends BeforeLoopBodyAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * Visit a foreach loop
	 *
	 * We do that in this visitor so that we can handle the loop condition prior to
	 * determine the taint of the loop variable, prior to evaluating the loop body.
	 * See https://github.com/phan/phan/issues/3936
	 *
	 * @param Node $node
	 */
	public function visitForeach( Node $node ): void {
		$expr = $node->children['expr'];
		$lhsTaintednessWithError = $this->getTaintedness( $expr );
		$lhsTaintedness = $lhsTaintednessWithError->getTaintedness();
		$lhsLinks = $lhsTaintednessWithError->getMethodLinks();

		$value = $node->children['value'];
		if ( $value->kind === \ast\AST_REF ) {
			// TODO, this doesn't propagate the taint to the outer scope
			// (FWIW, phan doesn't do much better with types, https://github.com/phan/phan/issues/4017)
			$value = $value->children['var'];
		}

		// TODO Actually compute this
		$rhsIsArray = false;
		// NOTE: As mentioned in test 'foreach', we won't be able to retroactively attribute
		// the right taint to the value if we discover what the key is for the current iteration
		$valueVisitor = new TaintednessAssignVisitor(
			$this->code_base,
			$this->context,
			$lhsTaintedness->asValueFirstLevel(),
			$lhsTaintednessWithError->getError(),
			$lhsLinks->asValueFirstLevel(),
			$lhsTaintedness->asValueFirstLevel(),
			$lhsLinks,
			$rhsIsArray
		);
		$valueVisitor( $value );

		$key = $node->children['key'] ?? null;
		if ( $key instanceof Node ) {
			$keyVisitor = new TaintednessAssignVisitor(
				$this->code_base,
				$this->context,
				$lhsTaintedness->asKeyForForeach(),
				$lhsTaintednessWithError->getError(),
				$lhsLinks->asKeyForForeach(),
				$lhsTaintedness->asKeyForForeach(),
				$lhsLinks,
				$rhsIsArray
			);
			$keyVisitor( $key );
		}
	}
}
