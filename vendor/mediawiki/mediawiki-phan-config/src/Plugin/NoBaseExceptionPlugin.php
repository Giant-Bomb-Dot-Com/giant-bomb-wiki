<?php

declare( strict_types=1 );

namespace MediaWikiPhanConfig\Plugin;

use Phan\PluginV3;
use Phan\PluginV3\PostAnalyzeNodeCapability;

class NoBaseExceptionPlugin extends PluginV3 implements PostAnalyzeNodeCapability {
	public const ISSUE_TYPE = 'MediaWikiNoBaseException';

	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return NoBaseExceptionVisitor::class;
	}
}

return new NoBaseExceptionPlugin();
