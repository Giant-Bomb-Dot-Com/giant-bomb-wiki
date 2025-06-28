<?php

declare( strict_types=1 );

namespace MediaWikiPhanConfig\Plugin;

use Phan\PluginV3;
use Phan\PluginV3\PostAnalyzeNodeCapability;

class NoEmptyIfDefinedPlugin extends PluginV3 implements PostAnalyzeNodeCapability {
	public const ISSUE_TYPE = 'MediaWikiNoEmptyIfDefined';

	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return NoEmptyIfDefinedVisitor::class;
	}
}

return new NoEmptyIfDefinedPlugin();
