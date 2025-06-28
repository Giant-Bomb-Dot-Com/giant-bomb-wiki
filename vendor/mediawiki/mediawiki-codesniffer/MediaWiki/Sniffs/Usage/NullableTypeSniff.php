<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforce use of `?MyClass $x = null` or `?MyClass $x` instead of `MyClass $x = null`, which is deprecated in php8.4.
 */
class NullableTypeSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register(): array {
		return [ T_FUNCTION, T_CLOSURE, T_FN ];
	}

	/**
	 * @param File $phpcsFile File
	 * @param int $stackPtr Location
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$params = $phpcsFile->getMethodParameters( $stackPtr );

		foreach ( $params as $param ) {
			if (
				$param['type_hint'] &&
				$param['nullable_type'] === false &&
				array_key_exists( 'default', $param ) &&
				$param['default'] === 'null'
			) {
				$fix = $phpcsFile->addFixableError(
					'Use PHP 8.4 compatible syntax for explicit nullable types ("?%s %s = %s")',
					$param['type_hint_token'],
					'ExplicitNullableTypes',
					[ $param['type_hint'], $param['name'], $param['default'] ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->addContentBefore( $param['type_hint_token'], '?' );
				}
			}
		}
	}
}
