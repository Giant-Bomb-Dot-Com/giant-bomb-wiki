<?php
use MediaWiki\Html\TemplateParser;

if ( !isset( $gameData ) || !is_array( $gameData ) ) {
    return;
}

$index = isset( $index ) ? intval( $index ) + 1 : 1;

// Prepare data for Mustache
$tplData = [
    'index' => $index,
    'img'   => $gameData['img'],
    'title' => $gameData['title'],
    'date'  => $gameData['date'],
    'desc'  => $gameData['desc'],
];

// Create TemplateParser instance
$templateParser = new TemplateParser( __DIR__ );

// Render and echo
echo $templateParser->processTemplate( 'game-card', $tplData );
