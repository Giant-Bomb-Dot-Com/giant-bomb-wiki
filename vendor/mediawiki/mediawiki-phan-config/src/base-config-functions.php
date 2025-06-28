<?php

use MediaWikiPhanConfig\ConfigBuilder;

function setBaseOptions( string $curDir, ConfigBuilder $configBuilder ): void {
	// TODO: Do we need to explicitly set these? If so, move to ConfigBuilder. Remove otherwise.
	$baseOptions = [
		'backward_compatibility_checks' => false,

		'parent_constructor_required' => [
		],

		'quick_mode' => false,
		'analyze_signature_compatibility' => true,
		'ignore_undeclared_variables_in_global_scope' => false,
		'read_type_annotations' => true,
		'disable_suppression' => false,
		'dump_ast' => false,
		'dump_signatures_file' => null,
		'processes' => 1,
		'whitelist_issue_types' => [],
		'markdown_issue_messages' => false,
		'generic_types_enabled' => true,
		'plugin_config' => [],
		// BC for repos not checking whether these are set
		'file_list' => [],
		'exclude_file_list' => [],
	];
	$configBuilder->setRawOptions( $baseOptions );

	$configBuilder
		->excludeDirectories( $curDir . '/stubs' )
		->setExcludeFileRegex(
			'@vendor/(' .
			'(' . implode( '|', array_merge( [
				// Exclude known dev dependencies
				'composer/installers',
				'php-parallel-lint/php-console-color',
				'php-parallel-lint/php-console-highlighter',
				'php-parallel-lint/php-parallel-lint',
				'mediawiki/mediawiki-codesniffer',
				'microsoft/tolerant-php-parser',
				'phan/phan',
				'phpunit/php-code-coverage',
				'squizlabs/php_codesniffer',
				// Exclude stubs used in libraries
				'[^/]+/[^/]+/\.phan',
			], PHP_MAJOR_VERSION < 8 ? [] : [
				'symfony/polyfill-php80',
			] ) ) . ')' .
			'|' .
			// Also exclude tests folder from dependencies
			'.*/[Tt]ests?' .
			')/@'
		)
		->setMinimumSeverity( 0 )
		->allowMissingProperties( false )
		->allowNullCastsAsAnyType( false )
		->allowScalarImplicitCasts( false )
		->enableDeadCodeDetection( false )
		->shouldDeadCodeDetectionPreferFalseNegatives( true )
		// TODO Enable by default
		->setProgressBarMode( ConfigBuilder::PROGRESS_BAR_DISABLED )
		->readClassAliases( true )
		->enableRedundantConditionDetection( true )
		->setMinimumPHPVersion( '7.4' )
		->setTargetPHPVersion( '8.1' )
		->addPlugins( [
			'PregRegexCheckerPlugin',
			'UnusedSuppressionPlugin',
			'DuplicateExpressionPlugin',
			'LoopVariableReusePlugin',
			'RedundantAssignmentPlugin',
			'UnreachableCodePlugin',
			'SimplifyExpressionPlugin',
			'DuplicateArrayKeyPlugin',
			'UseReturnValuePlugin',
			'AddNeverReturnTypePlugin',
		] )
		->addCustomPlugins( [
			'NoEmptyIfDefinedPlugin',
			'NoBaseExceptionPlugin',
		] );

	if ( !defined( 'MSG_EOR' ) ) {
		$configBuilder->addFiles( $curDir . '/stubs/sockets.windows.php' );
	}
}

/**
 * Internal helper used to filter dirs. This is used so that we can include commonly-used dir
 * names without phan complaining about "directory not found". It should NOT be used in
 * repo-specific config files.
 */
function filterDirs( array $dirs ): array {
	return array_filter( $dirs, 'file_exists' );
}
