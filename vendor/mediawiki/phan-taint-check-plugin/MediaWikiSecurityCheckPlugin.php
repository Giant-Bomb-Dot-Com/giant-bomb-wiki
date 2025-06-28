<?php
/**
 * Static analysis tool for MediaWiki extensions.
 *
 * To use, add this file to your phan plugins list.
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
 *
 */

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\GenericArrayType;
use SecurityCheckPlugin\FunctionTaintedness;
use SecurityCheckPlugin\MWPreVisitor;
use SecurityCheckPlugin\MWVisitor;
use SecurityCheckPlugin\SecurityCheckPlugin;
use SecurityCheckPlugin\Taintedness;
use SecurityCheckPlugin\TaintednessVisitor;

class MediaWikiSecurityCheckPlugin extends SecurityCheckPlugin {
	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return MWVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreAnalyzeNodeVisitorClassName(): string {
		return MWPreVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function getCustomFuncTaints(): array {
		$shellCommandOutput = [
			// This is a bit unclear. Most of the time
			// you should probably be escaping the results
			// of a shell command, but not all the time.
			'overall' => self::YES_TAINT
		];

		$insertTaint = new FunctionTaintedness( Taintedness::newSafe() );
		// table name
		$insertTaint->setParamSinkTaint( 0, new Taintedness( self::SQL_EXEC_TAINT ) );
		$insertTaint->addParamFlags( 0, self::NO_OVERRIDE );
		// Insert values. The keys names are unsafe. The argument can be either a single row or an array of rows.
		// Note, here we are assuming the single row case. The multiple rows case is handled in modifyParamSinkTaint.
		$sqlExecKeysTaint = Taintedness::newSafe();
		$sqlExecKeysTaint->addKeysTaintedness( self::SQL_EXEC_TAINT );
		$insertTaint->setParamSinkTaint( 1, $sqlExecKeysTaint );
		$insertTaint->addParamFlags( 1, self::NO_OVERRIDE );
		// method name
		$insertTaint->setParamSinkTaint( 2, new Taintedness( self::SQL_EXEC_TAINT ) );
		$insertTaint->addParamFlags( 2, self::NO_OVERRIDE );
		// options. They are not escaped
		$insertTaint->setParamSinkTaint( 3, new Taintedness( self::SQL_EXEC_TAINT ) );
		$insertTaint->addParamFlags( 3, self::NO_OVERRIDE );

		$insertQBRowTaint = new FunctionTaintedness( Taintedness::newSafe() );
		$insertQBRowTaint->setParamSinkTaint( 0, clone $sqlExecKeysTaint );
		$insertQBRowTaint->addParamFlags( 0, self::NO_OVERRIDE );

		$insertQBRowsTaint = new FunctionTaintedness( Taintedness::newSafe() );
		$multiRowsTaint = Taintedness::newSafe();
		$multiRowsTaint->setOffsetTaintedness( null, clone $sqlExecKeysTaint );
		$insertQBRowsTaint->setParamSinkTaint( 0, $multiRowsTaint );
		$insertQBRowsTaint->addParamFlags( 0, self::NO_OVERRIDE );

		return [
			// Note, at the moment, this checks where the function
			// is implemented, so you can't use IDatabase.
			'\Wikimedia\Rdbms\IDatabase::insert' => $insertTaint,
			'\Wikimedia\Rdbms\IMaintainableDatabase::insert' => $insertTaint,
			'\Wikimedia\Rdbms\Database::insert' => $insertTaint,
			'\Wikimedia\Rdbms\DBConnRef::insert' => $insertTaint,
			'\Wikimedia\Rdbms\InsertQueryBuilder::row' => $insertQBRowTaint,
			'\Wikimedia\Rdbms\InsertQueryBuilder::rows' => $insertQBRowsTaint,
			// FIXME Doesn't handle array args right.
			'\wfShellExec' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfShellExecWithStderr' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfEscapeShellArg' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Shell::escape' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Command::unsafeParams' => [
				self::SHELL_EXEC_TAINT | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Result::getStdout' => $shellCommandOutput,
			'\MediaWiki\Shell\Result::getStderr' => $shellCommandOutput,
			// Methods from wikimedia/Shellbox
			'\Shellbox\Shellbox::escape' => [
				( self::YES_TAINT & ~self::SHELL_TAINT ) | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\Shellbox\Command\Command::unsafeParams' => [
				self::SHELL_EXEC_TAINT | self::VARIADIC_PARAM,
				'overall' => self::NO_TAINT
			],
			'\Shellbox\Command\UnboxedResult::getStdout' => $shellCommandOutput,
			'\Shellbox\Command\UnboxedResult::getStderr' => $shellCommandOutput,
			// The value of a status object can be pretty much anything, with any degree of taintedness
			// and escaping. Since it's a widely used class, it will accumulate a lot of links and taintedness
			// offset, resulting in huge objects (the short string representation of those Taintedness objects
			// can reach lengths in the order of tens of millions).
			// Since the plugin cannot keep track the taintedness of a property per-instance (as it assumes that
			// every property will be used with the same escaping level), we just annotate the methods as safe.
			'\StatusValue::newGood' => [
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Status::newGood' => [
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\StatusValue::getValue' => [
				'overall' => self::NO_TAINT
			],
			'\Status::getValue' => [
				'overall' => self::NO_TAINT
			],
			'\StatusValue::setResult' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Status::setResult' => [
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
		];
	}

	/**
	 * Mark XSS's that happen in a Maintenance subclass as false a positive
	 *
	 * @inheritDoc
	 */
	public function isFalsePositive(
		int $combinedTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	): bool {
		if ( $combinedTaint === self::HTML_TAINT ) {
			$path = str_replace( '\\', '/', $context->getFile() );
			if (
				strpos( $path, 'maintenance/' ) === 0 ||
				strpos( $path, '/maintenance/' ) !== false
			) {
				// For classes not using Maintenance subclasses
				$msg .= ' [Likely false positive because in maintenance subdirectory, thus probably CLI]';
				return true;
			}
			if ( !$context->isInClassScope() ) {
				return false;
			}
			$maintFQSEN = FullyQualifiedClassName::fromFullyQualifiedString(
				'\\Maintenance'
			);
			if ( !$code_base->hasClassWithFQSEN( $maintFQSEN ) ) {
				return false;
			}
			$classFQSEN = $context->getClassFQSEN();
			$isMaint = TaintednessVisitor::isSubclassOf( $classFQSEN, $maintFQSEN, $code_base );
			if ( $isMaint ) {
				$msg .= ' [Likely false positive because in a subclass of Maintenance, thus probably CLI]';
				return true;
			}
		}
		return false;
	}

	/**
	 * Special-case the $rows argument to Database::insert (T290563)
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function modifyParamSinkTaint(
		Taintedness $paramSinkTaint,
		Taintedness $curArgTaintedness,
		Node $argument,
		int $argIndex,
		FunctionInterface $func,
		FunctionTaintedness $funcTaint,
		Context $context,
		CodeBase $code_base
	): Taintedness {
		if ( !$func instanceof Method || $argIndex !== 1 || $func->getName() !== 'insert' ) {
			return $paramSinkTaint;
		}

		$classFQSEN = $func->getClassFQSEN();
		if ( $classFQSEN->__toString() !== '\\Wikimedia\\Rdbms\\Database' ) {
			$idbFQSEN = FullyQualifiedClassName::fromFullyQualifiedString( '\\Wikimedia\\Rdbms\\IDatabase' );
			$isDBSubclass = $classFQSEN->asType()->asExpandedTypes( $code_base )->hasType( $idbFQSEN->asType() );
			if ( !$isDBSubclass ) {
				return $paramSinkTaint;
			}
		}

		$argType = UnionTypeVisitor::unionTypeFromNode( $code_base, $context, $argument );
		$keyType = GenericArrayType::keyUnionTypeFromTypeSetStrict( $argType->getTypeSet() );
		if ( $keyType !== GenericArrayType::KEY_INT ) {
			// Note, it might still be an array of rows, but it's too hard for us to tell.
			return $paramSinkTaint;
		}

		// Definitely a list of rows, so remove taintedness from the outer array keys, and instead add it to the
		// keys of inner arrays.
		$sqlExecKeysTaint = Taintedness::newSafe();
		$sqlExecKeysTaint->addKeysTaintedness( self::SQL_EXEC_TAINT );
		$adjustedParamTaint = Taintedness::newSafe();
		$adjustedParamTaint->setOffsetTaintedness( null, $sqlExecKeysTaint );
		return $adjustedParamTaint;
	}

	/**
	 * Disable double escape checking for messages with polymorphic methods
	 *
	 * A common cause of false positives for double escaping is that some
	 * methods take a string|Message, and this confuses the tool given
	 * the __toString() behaviour of Message. So disable double escape
	 * checking for that.
	 *
	 * This is quite hacky. Ideally the tool would treat methods taking
	 * multiple types as separate for each type, and also be able to
	 * reason out simple conditions of the form if ( $arg instanceof Message ).
	 * However that's much more complicated due to dependence on phan.
	 *
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function modifyArgTaint(
		Taintedness $curArgTaintedness,
		Node $argument,
		int $argIndex,
		FunctionInterface $func,
		FunctionTaintedness $funcTaint,
		Context $context,
		CodeBase $code_base
	): Taintedness {
		if ( $curArgTaintedness->has( self::ESCAPED_TAINT ) ) {
			$argumentIsMaybeAMsg = false;
			/** @var \Phan\Language\Element\Clazz[] $classes */
			$classes = UnionTypeVisitor::unionTypeFromNode( $code_base, $context, $argument )
				->asClassList( $code_base, $context );
			try {
				foreach ( $classes as $cl ) {
					if ( $cl->getFQSEN()->__toString() === '\Message' ) {
						$argumentIsMaybeAMsg = true;
						break;
					}
				}
			} catch ( CodeBaseException $_ ) {
				// A class that doesn't exist, don't crash.
				return $curArgTaintedness;
			}

			$param = $func->getParameterForCaller( $argIndex );
			if ( !$argumentIsMaybeAMsg || !$param || !$param->getUnionType()->hasStringType() ) {
				return $curArgTaintedness;
			}
			/** @var \Phan\Language\Element\Clazz[] $classesParam */
			$classesParam = $param->getUnionType()->asClassList( $code_base, $context );
			try {
				foreach ( $classesParam as $cl ) {
					if ( $cl->getFQSEN()->__toString() === '\Message' ) {
						// So we are here. Input is a Message, and func expects either a Message or string
						// (or something else). So disable double escape check.
						return $curArgTaintedness->without( self::ESCAPED_TAINT );
					}
				}
			} catch ( CodeBaseException $_ ) {
				// A class that doesn't exist, don't crash.
				return $curArgTaintedness;
			}
		}
		return $curArgTaintedness;
	}
}

return new MediaWikiSecurityCheckPlugin;
