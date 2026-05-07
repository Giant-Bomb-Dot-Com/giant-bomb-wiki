<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2023 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Trait that helps to mock the result of RevisionLookup::getRevisionById()
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use TextContent;

/**
 * @method static \PHPUnit\Framework\MockObject\Rule\AnyInvokedCount any()
 * @method static \PHPUnit\Framework\Constraint\IsIdentical identicalTo($a)
 * @method static \PHPUnit\Framework\MockObject\Stub\ReturnCallback returnCallback($a)
 */
trait MockRevisionLookupTestTrait
{
    /**
     * Return mock of RevisionLookup that will return $text as current text of revision $revid.
     * @param int $revid
     * @param string|null $text If null, RevisionRecord won't be found.
     * @param Title $title
     * @return RevisionLookup
     */
    public function mockRevisionLookup($revid, $text, Title $title)
    {
        $revisionLookup = $this->createMock(RevisionLookup::class);
        $revisionLookup
            ->expects($this->any())
            ->method("getRevisionById")
            ->with($this->identicalTo($revid))
            ->will(
                $this->returnCallback(static function ($id, $flags) use (
                    $text,
                    $title,
                ) {
                    if ($text === null) {
                        // Similate situation when RevisionRecord wasn't not found.
                        return null;
                    }

                    $rec = new MutableRevisionRecord($title);
                    $rec->setContent(SlotRecord::MAIN, new TextContent($text));
                    return $rec;
                }),
            );

        return $revisionLookup;
    }

    // These methods are in MediaWikiIntegrationTestCase (this trait is used by its subclasses).

    /** @inheritDoc */
    abstract protected function createMock(string $originalClassName);
}
