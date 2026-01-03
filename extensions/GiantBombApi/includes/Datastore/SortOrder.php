<?php

namespace GiantBombApi\Datastore;

/**
 * Sort order of database entries.
 */
enum SortOrder: string
{
    case Default = '';
    case NameAsc = 'name:asc';
    case NameDesc = 'name:desc';

    /**
     * List of all the cases as string values.
     */
    public static function values(): array {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
