<?php

namespace MediaWiki\Extension\AlgoliaSearch\Test;

use MediaWikiIntegrationTestCase;
use MediaWiki\Extension\AlgoliaSearch\RecordMapper;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\SlotRecord;
use Title;

/**
 * @covers \MediaWiki\Extension\AlgoliaSearch\RecordMapper::mapRecord
 * @covers \MediaWiki\Extension\AlgoliaSearch\RecordMapper::stripLegacyDisambigSuffix
 * @group Database
 */
class RecordMapperTest extends MediaWikiIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setMwGlobals("wgCanonicalServer", "https://example.org");
    }

    private function createPage(
        string $titleText,
        string $additionalContent = "",
    ): Title {
        $title = Title::newFromText($titleText);
        $this->assertNotNull($title, "Title must be created");
        $services = $this->getServiceContainer();
        $page = $services->getWikiPageFactory()->newFromTitle($title);
        $user = $this->getTestUser()->getUser();
        $updater = $page->newPageUpdater($user);
        $wikitext = "== Heading ==\nPage content.\n\n[[Category:Test]]";
        if ($additionalContent !== "") {
            $wikitext .= "\n" . $additionalContent;
        }
        $updater->setContent(SlotRecord::MAIN, new WikitextContent($wikitext));
        $updater->saveRevision(CommentStoreComment::newUnsavedComment("test"));
        return $title;
    }

    public function testMapRecordForGame(): void
    {
        $title = $this->createPage("Games/Test Game");
        $record = RecordMapper::mapRecord("Game", $title);
        $this->assertIsArray($record);
        $this->assertSame("Game", $record["type"]);
        $this->assertStringStartsWith("wiki:", $record["objectID"]);
        $this->assertNotEmpty($record["href"]);
        $this->assertArrayHasKey("excerpt", $record);
        $this->assertArrayHasKey("thumbnail", $record);
    }

    public function testMapRecordForCharacter(): void
    {
        $title = $this->createPage("Characters/Test Character");
        $record = RecordMapper::mapRecord("Character", $title);
        $this->assertIsArray($record);
        $this->assertSame("Character", $record["type"]);
        $this->assertStringStartsWith("wiki:", $record["objectID"]);
    }

    public function testMapRecordForConcept(): void
    {
        $title = $this->createPage("Concepts/Test Concept");
        $record = RecordMapper::mapRecord("Concept", $title);
        $this->assertIsArray($record);
        $this->assertSame("Concept", $record["type"]);
        $this->assertStringStartsWith("wiki:", $record["objectID"]);
    }

    public function testLegacyImageDataFallbackProvidesThumbnail(): void
    {
        $json =
            '{"infobox":{"file":"legacy-cover.jpg","path":"9\/93770\/","mime":"image\/jpeg","sizes":"screen_kubrick,scale_super","caption":"Legacy cover"}}';
        $div =
            '<div id="imageData" data-json="' .
            htmlspecialchars($json, ENT_QUOTES) .
            '"></div>';
        $title = $this->createPage("Games/Legacy Image", $div);
        $record = RecordMapper::mapRecord("Game", $title);
        $this->assertIsArray($record);
        $this->assertSame(
            "https://example.org/a/uploads/screen_kubrick/9/93770/legacy-cover.jpg",
            $record["thumbnail"],
        );
    }

    public function testUnsupportedTypeReturnsNull(): void
    {
        $title = $this->createPage("Misc/Unsupported");
        $this->assertNull(RecordMapper::mapRecord("Release", $title));
    }

    public function testCategoriesExcludeHiddenCategories(): void
    {
        $services = $this->getServiceContainer();
        $user = $this->getTestUser()->getUser();

        // Create a hidden tracking category page
        $catTitle = Title::newFromText(
            "Category:Pages with empty short description",
        );
        $catPage = $services->getWikiPageFactory()->newFromTitle($catTitle);
        $updater = $catPage->newPageUpdater($user);
        $updater->setContent(
            SlotRecord::MAIN,
            new WikitextContent("__HIDDENCAT__"),
        );
        $updater->saveRevision(
            CommentStoreComment::newUnsavedComment("create hidden cat"),
        );

        // Create a game page in both the real category and the hidden one
        $title = $this->createPage(
            "Games/Hidden Cat Test",
            "[[Category:Pages with empty short description]]",
        );

        $record = RecordMapper::mapRecord("Game", $title);
        $this->assertIsArray($record);
        $this->assertContains("Test", $record["categories"]);
        $this->assertNotContains(
            "Pages with empty short description",
            $record["categories"],
            "Hidden tracking categories should be excluded",
        );
    }

	/**
	 * @dataProvider provideLegacyDisambigSuffixCases
	 */
	public function testStripLegacyDisambigSuffix( string $input, string $expected, string $why ): void {
		$this->assertSame(
			$expected,
			RecordMapper::stripLegacyDisambigSuffix( $input ),
			$why
		);
	}

	public function provideLegacyDisambigSuffixCases(): array {
		return [
			// Cases observed in production (5-digit GB-API ids on disambiguated pages).
			'space-separated 5-digit id'    => [ 'Sprout 64629',          'Sprout',         'screenshot example' ],
			'underscore-separated 5-digit'  => [ 'Sprout_64629',          'Sprout',         'raw slug form before underscore->space conversion' ],
			'multi-word with 5-digit id'    => [ 'Diamond Quest 65309',   'Diamond Quest',  'multi-word name keeps internal spaces' ],
			'6-digit id'                    => [ 'Foo 123456',            'Foo',            'longer ids than the screenshot range' ],

			// Legitimate digit-ending names that must survive.
			'1-digit sequel'                => [ 'Tekken 7',              'Tekken 7',       '<5 digits => not a disambig id' ],
			'2-digit year'                  => [ 'Madden 99',             'Madden 99',      'late-90s sports titles' ],
			'3-digit number'                => [ 'Game 100',              'Game 100',       'still under threshold' ],
			'4-digit year'                  => [ 'Madden NFL 2008',       'Madden NFL 2008','sports games with full year' ],
			'4-digit retro number'          => [ 'Halo 2600',             'Halo 2600',      'Atari 2600 demake; 4 digits but legit' ],
			'no trailing digits'            => [ 'Final Fantasy VII',     'Final Fantasy VII', 'Roman numerals untouched' ],
			'plain word'                    => [ 'Sprout',                'Sprout',         'no-op when nothing to strip' ],

			// Edge cases.
			'empty string'                  => [ '',                      '',               'no crash on empty' ],
			'only digits, no separator'     => [ '12345',                 '12345',          'must have leading space/underscore to strip' ],
			'digits glued to word'          => [ 'Foo64629',              'Foo64629',       'no space/underscore => no strip' ],
			'trailing space after digits'   => [ 'Sprout 64629   ',       'Sprout',         'rtrim trailing whitespace' ],
			'embedded id in middle'         => [ 'Sprout 64629 Reloaded', 'Sprout 64629 Reloaded', 'only end-anchored matches strip' ],
			'name with colon'               => [ 'Resident Evil 2: Remake 99999', 'Resident Evil 2: Remake', 'punctuation preserved, only suffix stripped' ],
		];
	}

	public function testFallbackTitleStripsLegacyDisambigSuffix(): void {
		$title = $this->createPage( 'Games/Sprout 64629' );
		$record = RecordMapper::mapRecord( 'Game', $title );
		$this->assertIsArray( $record );
		$this->assertSame( 'Sprout', $record['title'], 'fallback path strips trailing 5-digit id' );
	}

	public function testFallbackTitlePreservesLegitimateDigitSuffixes(): void {
		// "Tekken 7", "Madden 99", "Halo 2600", "Madden NFL 2008" must not be
		// clipped by the disambiguation stripper (threshold is >=5 digits).
		$cases = [
			'Games/Tekken 7'        => 'Tekken 7',
			'Games/Madden 99'       => 'Madden 99',
			'Games/Halo 2600'       => 'Halo 2600',
			'Games/Madden NFL 2008' => 'Madden NFL 2008',
		];
		foreach ( $cases as $pageTitle => $expectedTitle ) {
			$title = $this->createPage( $pageTitle );
			$record = RecordMapper::mapRecord( 'Game', $title );
			$this->assertSame( $expectedTitle, $record['title'], "$pageTitle should keep its trailing digits" );
		}
	}

	public function testFallbackTitleHandlesAllEntityTypes(): void {
		// All entity types share the same fallback path; confirm no type leaks
		// the disambig id through.
		$cases = [
			[ 'Game',      'Games/Sprout 64629',           'Sprout' ],
			[ 'Character', 'Characters/Hero Guy 50000',    'Hero Guy' ],
			[ 'Concept',   'Concepts/Open World 99999',    'Open World' ],
		];
		foreach ( $cases as [ $type, $pageTitle, $expected ] ) {
			$title = $this->createPage( $pageTitle );
			$record = RecordMapper::mapRecord( $type, $title );
			$this->assertNotNull( $record, "$type record should be produced" );
			$this->assertSame( $expected, $record['title'], "$type fallback must strip disambig id" );
		}
	}

	public function testCategoriesExcludeByPattern(): void {
		$this->setMwGlobals( 'wgAlgoliaExcludeCategoryPatterns', [
			'/^Pages using /i',
			'/^Articles with /i',
		] );

        $title = $this->createPage(
            "Games/Pattern Cat Test",
            "[[Category:Pages using duplicate arguments in template calls]]",
        );

        $record = RecordMapper::mapRecord("Game", $title);
        $this->assertIsArray($record);
        $this->assertContains("Test", $record["categories"]);
        $this->assertNotContains(
            "Pages using duplicate arguments in template calls",
            $record["categories"],
            "Categories matching exclude patterns should be filtered out",
        );
    }
}
