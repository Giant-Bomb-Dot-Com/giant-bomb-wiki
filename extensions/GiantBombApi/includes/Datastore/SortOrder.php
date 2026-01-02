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
}
