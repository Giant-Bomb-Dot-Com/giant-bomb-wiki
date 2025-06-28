<?php

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\Parameter;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;

/**
 * Class for visiting any nodes we want to handle in pre-order.
 *
 * Unlike TaintednessVisitor, this is solely used to set taint
 * on variable objects, and not to determine the taint of the
 * current node, so this class does not return anything.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
class PreTaintednessVisitor extends PluginAwarePreAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * @see visitMethod
	 * @param Node $node
	 */
	public function visitFuncDecl( Node $node ): void {
		$this->visitMethod( $node );
	}

	/**
	 * @see visitMethod
	 * @param Node $node
	 */
	public function visitClosure( Node $node ): void {
		$this->visitMethod( $node );
	}

	/**
	 * @param Node $node
	 */
	public function visitArrowFunc( Node $node ): void {
		$this->visitMethod( $node );
	}

	/**
	 * Set the taintedness of parameters to method/function.
	 *
	 * Parameters that are ints (etc) are clearly safe so
	 * this marks them as such. For other parameters, it
	 * creates a map between the function object and the
	 * parameter object so if anyone later calls the method
	 * with a dangerous argument we can determine if we need
	 * to output a warning.
	 *
	 * Also handles FuncDecl and Closure
	 * @param Node $node
	 */
	public function visitMethod( Node $node ): void {
		// var_dump( __METHOD__ ); Debug::printNode( $node );
		$method = $this->context->getFunctionLikeInScope( $this->code_base );
		// Initialize retObjs to avoid recursing on methods that don't return anything.
		self::initRetObjs( $method );
		$promotedProps = [];
		if ( $node->kind === \ast\AST_METHOD && $node->children['name'] === '__construct' ) {
			foreach ( $method->getParameterList() as $i => $param ) {
				if ( $param->getFlags() & Parameter::PARAM_MODIFIER_FLAGS ) {
					$promotedProps[$i] = $this->getPropInCurrentScopeByName( $param->getName() );
				}
			}
		}

		$params = $node->children['params']->children;
		foreach ( $params as $i => $param ) {
			$paramName = $param->children['name'];
			$scope = $this->context->getScope();
			if ( !$scope->hasVariableWithName( $paramName ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $paramName );
				continue;
			}
			$varObj = $scope->getVariableByName( $paramName );

			$paramTypeTaint = $this->getTaintByType( $varObj->getUnionType() );
			// Initially, the variable starts off with no taint.
			$startTaint = new Taintedness( SecurityCheckPlugin::NO_TAINT );
			// No point in adding a caused-by line here.
			self::setTaintednessRaw( $varObj, $startTaint );

			if ( !$paramTypeTaint->isSafe() ) {
				// If the param is not an integer or something, link it to the func
				$this->linkParamAndFunc( $varObj, $method, $i );
			}
			if ( isset( $promotedProps[$i] ) ) {
				$this->ensureTaintednessIsSet( $promotedProps[$i] );
				$paramLinks = self::getMethodLinks( $varObj );
				if ( $paramLinks ) {
					$this->mergeTaintDependencies( $promotedProps[$i], $paramLinks, false );
				}
				$this->addTaintError( $promotedProps[$i], $startTaint, $paramLinks );
			}
		}

		if ( !self::getFuncTaint( $method ) ) {
			$this->getSetKnownTaintOfFunctionWithoutAnalysis( $method );
		}
	}

	/**
	 * Determine whether this operation is safe, based on the operand types. This needs to be done
	 * in preorder because phan infers types from operators, e.g. from `$a += $b` phan will infer
	 * that they're both numbers. We need to use the types of the operands *before* inferring
	 * types from the operator.
	 *
	 * @param Node $node
	 */
	public function visitAssignOp( Node $node ): void {
		$lhs = $node->children['var'];
		$rhs = $node->children['expr'];
		// @phan-suppress-next-line PhanUndeclaredProperty
		$node->assignTaintMask = $this->getBinOpTaintMask( $node, $lhs, $rhs );
	}

	/**
	 * When a class property is declared
	 * @param Node $node
	 */
	public function visitPropElem( Node $node ): void {
		$prop = $this->getPropInCurrentScopeByName( $node->children['name'] );
		$this->ensureTaintednessIsSet( $prop );
	}
}
