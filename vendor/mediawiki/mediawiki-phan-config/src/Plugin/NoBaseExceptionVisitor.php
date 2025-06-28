<?php

declare( strict_types=1 );

namespace MediaWikiPhanConfig\Plugin;

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use const ast\AST_NAME;

class NoBaseExceptionVisitor extends PluginAwarePostAnalysisVisitor {
	/**
	 * @inheritDoc
	 */
	public function visitNew( Node $node ): void {
		$classNode = $node->children['class'];
		if ( !$classNode instanceof Node ) {
			// Syntax error, bail out
			return;
		}
		if ( $classNode->kind !== AST_NAME ) {
			// A variable or another dynamic expression, skip for now.
			// TODO We could try and handle this, at least in the easy cases (e.g., if the value of the variable
			// can be inferred statically).
			// Note that we can't just check the union type, because it could be `Exception` even if the class is not
			// `Exception` itself (e.g., if the union type is inferred from a doc comment or typehint that only uses
			// `Exception` as the ancestor type of all the potential values).
			return;
		}
		if ( !isset( $classNode->children['name'] ) ) {
			// Syntax error, bail out
			return;
		}

		try {
			$classNameType = UnionTypeVisitor::unionTypeFromClassNode(
				$this->code_base,
				$this->context,
				$classNode
			);
		} catch ( IssueException | FQSENException $_ ) {
			return;
		}

		if ( $classNameType->typeCount() !== 1 ) {
			// Shouldn't happen
			return;
		}
		$classType = $classNameType->getTypeSet()[0];
		if ( !$classType->isObjectWithKnownFQSEN() ) {
			// Syntax error or something.
			return;
		}
		$classFQSEN = $classType->asFQSEN();
		if ( !$classFQSEN instanceof FullyQualifiedClassName ) {
			// Should not happen.
			return;
		}

		$classFQSENStr = (string)$classFQSEN;
		if ( $classFQSENStr === '\Exception' ) {
			self::emitPluginIssue(
				$this->code_base,
				$this->context,
				NoBaseExceptionPlugin::ISSUE_TYPE,
				// Links to https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#Exception_handling
				'Instantiating {CLASS} directly is not allowed. Use SPL exceptions if the exception is ' .
					'unchecked, or define a custom exception class otherwise. See https://w.wiki/6nur',
				[ $classFQSENStr ]
			);
		}
	}
}
