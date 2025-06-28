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

use MediaWikiPhanConfig\MediaWikiConfigBuilder;

require_once __DIR__ . '/base-config-functions.php';

// Replace \\ by / for windows users to let exclude work correctly
$DIR = str_replace( '\\', '/', __DIR__ );

// TODO: Use \Phan\Config::projectPath()
$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../..';

$VP = getenv( 'MW_VENDOR_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_VENDOR_PATH' ) )
	: $IP;

$baseCfg = new MediaWikiConfigBuilder( $IP );
setBaseOptions( $DIR, $baseCfg );

$baseCfg
	->setDirectoryList( filterDirs( [
		'includes/',
		'src/',
		'maintenance/',
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
	] ) )
	->setExcludedDirectoryList( [
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',

	] )
	->setSuppressedIssuesList( [
		// Deprecation warnings
		'PhanDeprecatedFunction',
		'PhanDeprecatedClass',
		'PhanDeprecatedClassConstant',
		'PhanDeprecatedFunctionInternal',
		'PhanDeprecatedInterface',
		'PhanDeprecatedProperty',
		'PhanDeprecatedTrait',

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

		// https://github.com/phan/phan/issues/3420
		'PhanAccessClassConstantInternal',
		'PhanAccessClassInternal',
		'PhanAccessConstantInternal',
		'PhanAccessMethodInternal',
		'PhanAccessPropertyInternal',

		// These are quite PHP8-specific
		'PhanParamNameIndicatingUnused',
		'PhanParamNameIndicatingUnusedInClosure',
		'PhanProvidingUnusedParameter',

		// Would probably have many false positives
		'PhanPluginMixedKeyNoKey',
	] )
	->addGlobalsWithTypes( [
		'wgContLang' => '\\Language',
		'wgParser' => '\\Parser',
		'wgTitle' => '\\MediaWiki\\Title\\Title',
		'wgMemc' => '\\BagOStuff',
		'wgUser' => '\\User',
		'wgConf' => file_exists( "$IP/includes/config/SiteConfiguration.php" )
			? '\\MediaWiki\\Config\\SiteConfiguration' : '\\SiteConfiguration',
		'wgLang' => '\\Language',
		'wgOut' => '\\MediaWiki\\Output\\OutputPage',
		'wgRequest' => file_exists( "$IP/includes/Request/WebRequest.php" )
			? '\\MediaWiki\\Request\\WebRequest' : '\\WebRequest',
	] )
	->enableTaintCheck( $DIR, $VP );

// BC: We're not ready to use the ConfigBuilder everywhere
return $baseCfg->make();
