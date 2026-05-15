<?php

namespace MediaWiki\Skin\GiantBomb\Test\Helpers;

use MediaWikiIntegrationTestCase;
use GiantBomb\Skin\Helpers\PageHelper;

/**
 * @covers \GiantBomb\Skin\Helpers\PageHelper::resolveDisplayNames
 * @group Database
 */
class PageHelperTest extends MediaWikiIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $IP;
        require_once "$IP/skins/GiantBomb/includes/helpers/PageHelper.php";
    }

    public function testResolveDisplayNamesWithEmptyArray(): void
    {
        $result = PageHelper::resolveDisplayNames([]);
        $this->assertSame([], $result);
    }

    public function testResolveDisplayNamesPreservesOrderAndFallsBackToInput(): void
    {
        // Missing pages should fall back to input
        $input = ["NonExistent Game 99999", "Another Missing Game 12345"];
        $result = PageHelper::resolveDisplayNames($input, "Games");
        $this->assertCount(2, $result);
        // No SMW data, should return input as-is
        $this->assertSame("NonExistent Game 99999", $result[0]);
        $this->assertSame("Another Missing Game 12345", $result[1]);
    }

    public function testResolveDisplayNamesWithSingleItem(): void
    {
        $input = ["Solo Game"];
        $result = PageHelper::resolveDisplayNames($input, "Games");
        $this->assertCount(1, $result);
        $this->assertSame("Solo Game", $result[0]);
    }

    public function testResolveDisplayNamesDefaultNamespace(): void
    {
        // Default namespace param
        $result = PageHelper::resolveDisplayNames(["Test"]);
        $this->assertCount(1, $result);
        $this->assertSame("Test", $result[0]);
    }
}
