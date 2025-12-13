<?php

namespace MediaWiki\Extension\AlgoliaSearch\Test;

use MediaWikiIntegrationTestCase;
use MediaWiki\Extension\AlgoliaSearch\RecordMapper;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\SlotRecord;
use Title;

/**
 * @covers \MediaWiki\Extension\AlgoliaSearch\RecordMapper
 * @group Database
 */
class RecordMapperTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgCanonicalServer', 'https://example.org' );
	}

	private function createPage( string $titleText, string $additionalContent = '' ): Title {
		$title = Title::newFromText( $titleText );
		$this->assertNotNull( $title, 'Title must be created' );
		$services = $this->getServiceContainer();
		$page = $services->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$updater = $page->newPageUpdater( $user );
		$wikitext = "== Heading ==\nPage content.\n\n[[Category:Test]]";
		if ( $additionalContent !== '' ) {
			$wikitext .= "\n" . $additionalContent;
		}
		$updater->setContent( SlotRecord::MAIN, new WikitextContent( $wikitext ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'test' ) );
		return $title;
	}

	public function testMapRecordForGame(): void {
		$title = $this->createPage( 'Games/Test Game' );
		$record = RecordMapper::mapRecord( 'Game', $title );
		$this->assertIsArray( $record );
		$this->assertSame( 'Game', $record['type'] );
		$this->assertStringStartsWith( 'wiki:', $record['objectID'] );
		$this->assertNotEmpty( $record['href'] );
		$this->assertArrayHasKey( 'excerpt', $record );
		$this->assertArrayHasKey( 'thumbnail', $record );
	}

	public function testMapRecordForCharacter(): void {
		$title = $this->createPage( 'Characters/Test Character' );
		$record = RecordMapper::mapRecord( 'Character', $title );
		$this->assertIsArray( $record );
		$this->assertSame( 'Character', $record['type'] );
		$this->assertStringStartsWith( 'wiki:', $record['objectID'] );
	}

	public function testMapRecordForConcept(): void {
		$title = $this->createPage( 'Concepts/Test Concept' );
		$record = RecordMapper::mapRecord( 'Concept', $title );
		$this->assertIsArray( $record );
		$this->assertSame( 'Concept', $record['type'] );
		$this->assertStringStartsWith( 'wiki:', $record['objectID'] );
	}

	public function testLegacyImageDataFallbackProvidesThumbnail(): void {
		$json = '{"infobox":{"file":"legacy-cover.jpg","path":"9\/93770\/","mime":"image\/jpeg","sizes":"screen_kubrick,scale_super","caption":"Legacy cover"}}';
		$div = '<div id="imageData" data-json="' . htmlspecialchars( $json, ENT_QUOTES ) . '"></div>';
		$title = $this->createPage( 'Games/Legacy Image', $div );
		$record = RecordMapper::mapRecord( 'Game', $title );
		$this->assertIsArray( $record );
		$this->assertSame(
			'https://example.org/a/uploads/screen_kubrick/9/93770/legacy-cover.jpg',
			$record['thumbnail']
		);
	}

	public function testUnsupportedTypeReturnsNull(): void {
		$title = $this->createPage( 'Misc/Unsupported' );
		$this->assertNull( RecordMapper::mapRecord( 'Release', $title ) );
	}
}


