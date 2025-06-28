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

use MediaWikiPhanConfig\ConfigBuilder;

require_once __DIR__ . '/base-config-functions.php';

// Replace \\ by / for windows users to let exclude work correctly
$DIR = str_replace( '\\', '/', __DIR__ );

$baseCfg = new ConfigBuilder();
setBaseOptions( $DIR, $baseCfg );

$baseCfg
	->setDirectoryList( filterDirs( [
		'includes/',
		'src/',
		'.phan/stubs/',
		'vendor/',
	] ) )
	->setExcludedDirectoryList( [
		'.phan/stubs/',
		'vendor/',
	] )
	->setSuppressedIssuesList( [
		// Covered by codesniffer
		'PhanUnreferencedUseNormal',
		'PhanUnreferencedUseFunction',
		'PhanUnreferencedUseConstant',
		'PhanDuplicateUseNormal',
		'PhanDuplicateUseFunction',
		'PhanDuplicateUseConstant',
		'PhanUseNormalNoEffect',
		'PhanUseNormalNamespacedNoEffect',
		'PhanUseFunctionNoEffect',
		'PhanUseConstantNoEffect',
		'PhanDeprecatedCaseInsensitiveDefine',

		// These are quite PHP8-specific
		'PhanParamNameIndicatingUnused',
		'PhanParamNameIndicatingUnusedInClosure',
		'PhanProvidingUnusedParameter',

		// Would probably have many false positives
		'PhanPluginMixedKeyNoKey',
	] )
	->enableTaintCheck( $DIR, 'vendor/' );

// BC: We're not ready to use the ConfigBuilder everywhere
return $baseCfg->make();
