<?php

namespace GiantBombApi\Helpers;

/**
 * Contains helpers relating to Wiki pages.
 */
class PageHelper {

    /**
     * Convert a Wiki page title to a human-readable one, removing the prefix
     * and converting underscores to spaces.
     * @param string $title Title to format.
     * @return string Formatted title.
     */
    public static function humanizeTitle( string $title ): string {
        return str_replace('_', ' ', substr($title, strpos($title, '/') + 1));
    }
}
