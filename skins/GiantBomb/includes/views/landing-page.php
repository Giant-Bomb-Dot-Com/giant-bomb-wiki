<?php
use MediaWiki\Html\TemplateParser;

$gamesJson = __DIR__ . '/../../resources/data/sampleGames.json';
$buttonsJson = __DIR__ . '/../../resources/data/categoryButtons.json';

// Get contents of JSON file
$gamesContent = file_get_contents($gamesJson);
$buttonsContent = file_get_contents($buttonsJson);

// Decode JSON into PHP array
$games = json_decode($gamesContent, true);
$buttons = json_decode($buttonsContent, true);

$hotGames = array_slice($games, 0, 3);
$tigGames = array_slice($games, 0, 6);
$randomGames = [...$games, ...$games];

$buttonData = [];

// Populate buttonData from buttons array
foreach ($buttons as $button) {
    $buttonData[] = [
        'title' => $button,
        'label' => $button
    ];
}

// Set Mustache data
$data = [
    'buttons' => $buttonData,
    'hotGames' => $hotGames,
    'tigGames' => $tigGames,
    'games' => $randomGames,
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates/landingPage');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('landing-page', $data);
