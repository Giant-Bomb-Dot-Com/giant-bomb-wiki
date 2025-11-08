<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * New releases page View
 * Displays the latest game releases, grouped by week
 */

$releases = [];

$user = RequestContext::getMain()->getUser();
$langFactory = MediaWikiServices::getInstance()->getLanguageFactory();
$lang = $langFactory->getLanguage('en');
try {
    $query = '[[Category:Games]]';
    
    $parser = MediaWikiServices::getInstance()->getParser();
    $options = new ParserOptions($user, $lang);
    
    $title = Title::newFromText('Dummy Title');
    
    $parsed = $parser->parse("{{#invoke:Common/SMW|run|query=$query}}", $title, $options, true);
    $rawText = $parsed->getRawText();
    $rawText = trim($rawText);
    // Substring the text before first '[' and last ']'
    $rawText = substr($rawText, strpos($rawText, '['));
    $rawText = substr($rawText, 0, strrpos($rawText, ']') + 1);
    
    // Replace double quotes in <a> tags with single quotes
    $rawText = preg_replace_callback(
        '#<a href="([^"]+)" title="([^"]+)">([^<]+)</a>#',
        function($matches) {
            return '<a href=\'' . $matches[1] . '\' title=\'' . $matches[2] . '\'>' . $matches[3] . '</a>';
        },
        $rawText
    );
    
    $rawData = json_decode($rawText, true);
    
    if ($rawData === null) {
        error_log("Error decoding JSON: " . json_last_error_msg());
    }
    
    foreach($rawData as $release) {
        if (isset($release[0])) {
            if (preg_match("#<a href='([^']+)' title='([^']+)'>([^<]+)</a>#", $release[0], $matches)) {
                $release['url'] = $matches[1];
                $release['title'] = $matches[2];
                $release['text'] = $matches[3];
                $releases[] = $release;
            }
        }
    }
}
catch (Exception $e) {
    error_log("Error querying releases: " . $e->getMessage());
    $releases = [];
}

// Format data for Mustache template
$data = [
    'releases' => $releases,
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('new-releases-page', $data);
