<?php

/**
 * Helper functions for querying data from Semantic MediaWiki
 */

function removeSpecialSMWQueryCharacters($value) {
    return str_replace(['[[', ']]', '[', ']', '|', '::', '*', '{', '}'], '', $value);
}
