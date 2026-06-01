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
 * Trait that helps to mock the result of EntryFactory::loadRow().
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\EntryFactory;
use stdClass;

/**
 * @method static \PHPUnit\Framework\MockObject\Rule\InvokedCount once()
 * @method static \PHPUnit\Framework\Constraint\IsIdentical identicalTo($a)
 */
trait MockLoadRowTestTrait
{
    /**
     * Returns mock of EntryFactory that will return $row when asked for $where and $fields.
     * @param int|array $where
     * @param array $fields
     * @param stdClass $row
     * @return EntryFactory
     */
    public function mockLoadRow($where, array $fields, $row)
    {
        $entryFactory = $this->createMock(EntryFactory::class);
        $entryFactory
            ->expects($this->once())
            ->method("loadRow")
            ->with($this->identicalTo($where), $this->identicalTo($fields))
            ->willReturn($row);

        return $entryFactory;
    }

    // These methods are in MediaWikiIntegrationTestCase (this trait is used by its subclasses).

    /** @inheritDoc */
    abstract protected function createMock(string $originalClassName);
}
