<?php

namespace MediaWiki\Extension\GBCatalogSync\Tests;

use MediaWiki\Extension\GBCatalogSync\Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\GBCatalogSync\Hooks
 */
class HooksTest extends TestCase
{
    private function mockTitle(string $text, int $namespace = NS_MAIN): \Title
    {
        $title = $this->createMock(\Title::class);
        $title->method("getText")->willReturn($text);
        $title->method("getNamespace")->willReturn($namespace);
        return $title;
    }

    public function testIsGameTitleMatchesDirectChildren(): void
    {
        $this->assertTrue(
            Hooks::isGameTitle($this->mockTitle("Games/Bubsy 4D")),
        );
        $this->assertFalse(
            Hooks::isGameTitle($this->mockTitle("Games/Bubsy 4D/Releases")),
        );
        $this->assertFalse(
            Hooks::isGameTitle($this->mockTitle("Characters/Bubsy")),
        );
        $this->assertFalse(Hooks::isGameTitle($this->mockTitle("Games")));
        $this->assertFalse(
            Hooks::isGameTitle($this->mockTitle("Games/Bubsy 4D", NS_TALK)),
        );
    }

    public function testExtractGuidLegacy(): void
    {
        $text = "{{Game\n| Name=Bubsy 4D\n| Guid=3030-22195\n}}";
        $this->assertSame("3030-22195", Hooks::extractGuid($text));
    }

    public function testExtractGuidInlineParams(): void
    {
        $text = "{{Game|Name=Bubsy 4D|Guid=3030-22195}}";
        $this->assertSame("3030-22195", Hooks::extractGuid($text));
    }

    public function testExtractGuidUuid(): void
    {
        $uuid = "6b9be6ce-4c8f-4d51-9e29-1a5b3e6c1d2f";
        $this->assertSame($uuid, Hooks::extractGuid("| Guid = {$uuid}\n"));
    }

    public function testExtractGuidSkipsInvalidMatchesForLaterValidOne(): void
    {
        $text =
            "<!-- | Guid=3030-1 old value -->\n" .
            "{{Game\n| Name=Bubsy 4D\n| Guid=3030-22195\n}}";
        $this->assertSame("3030-22195", Hooks::extractGuid($text));
    }

    public function testExtractGuidRejectsJunk(): void
    {
        $this->assertNull(Hooks::extractGuid(""));
        $this->assertNull(Hooks::extractGuid("no guid here"));
        $this->assertNull(Hooks::extractGuid("| Guid=\n"));
        $this->assertNull(Hooks::extractGuid("| Guid=not-a-guid\n"));
        $this->assertNull(Hooks::extractGuid("| Guid=3030-0\n"));
    }

    public function testExtractSeedTitlePrefersNameParam(): void
    {
        $title = $this->mockTitle("Games/Bubsy 4D");
        $text = "{{Game\n| Name=Bubsy the 4th\n| Guid=3030-22195\n}}";
        $this->assertSame(
            "Bubsy the 4th",
            Hooks::extractSeedTitle($text, $title),
        );
    }

    public function testExtractSeedTitleFallsBackToLeaf(): void
    {
        $title = $this->mockTitle("Games/Bubsy 4D");
        $this->assertSame("Bubsy 4D", Hooks::extractSeedTitle("", $title));
    }

    public function testExtractSeedTitleStripsLegacyDisambigId(): void
    {
        $title = $this->mockTitle("Games/Sprout 64629");
        $this->assertSame("Sprout", Hooks::extractSeedTitle("", $title));
    }

    public function testExtractSeedTitleKeepsYearNumbers(): void
    {
        $title = $this->mockTitle("Games/Madden NFL 2008");
        $this->assertSame(
            "Madden NFL 2008",
            Hooks::extractSeedTitle("", $title),
        );
    }
}
