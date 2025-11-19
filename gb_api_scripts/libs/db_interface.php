<?php
interface DbInterface 
{
    public const ALLOWED_TABLES_FOR_DESCRIPTION = [
        'wiki_accessory' => true,
        'wiki_character' => true,
        'wiki_company' => true,
        'wiki_concept' => true,
        'wiki_game' => true,
        'wiki_game_dlc' => true,
        'wiki_game_release' => true,
        'wiki_game_genre' => true,
        'wiki_game_theme' => true,
        'wiki_franchise' => true,
        'wiki_location' => true,
        'wiki_person' => true,
        'wiki_platform' => true,
        'wiki_thing' => true,
    ];

    public const ALLOWED_TABLES_FOR_PAGENAME = [
        'wiki_accessory' => true,
        'wiki_character' => true,
        'wiki_company' => true,
        'wiki_concept' => true,
        'wiki_game' => true,
        'wiki_game_genre' => true,
        'wiki_game_theme' => true,
        'wiki_franchise' => true,
        'wiki_location' => true,
        'wiki_person' => true,
        'wiki_platform' => true,
        'wiki_thing' => true,
    ];

    public function getDbw();
    public function hasResults($result): bool;
    public function getVersion(): string;
    public function getById(string $table, array $fields, int $id);
    public function getAll(string $table, array $fields, int $continue = 0);
    public function getPageName(string $table, int $id);
    public function getImageName(int $id);
    public function getRelatedPageNames(string $table, array $relationsMap, int $id);
    public function getCreditsFromDB(int $id);
    public function getReleasesFromDB(int $id);
    public function getDLCFromDB(int $id);
    public function getTextToConvert(string $table, $id = false, $force = false, $continue = 0);
    public function getNamesToConvert(string $table, $id = false, $force = false);
    public function updateMediaWikiDescription(string $table, int $id, string $mwDescription);
    public function updateMediaWikiPageName(string $table, int $id, string $mwPageName);
}
?>