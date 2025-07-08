<?php
use MediaWiki\Html\TemplateParser;

// Since Results loads after pageload, if TemplateParser isn’t loaded, require it manually
if ( !class_exists(TemplateParser::class) ) {
    require_once dirname(__DIR__, 4) . '/includes/WebStart.php';
}

$title = $_GET['title'] ?? 'SEARCH RESULT';

$templateParser = new TemplateParser(__DIR__ . '/../templates/landingPage');

echo $templateParser->processTemplate('results', [
    'title' => $title
]);
