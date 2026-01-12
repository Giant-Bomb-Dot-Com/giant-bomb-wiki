<?php
/**
 * Generates sample Character pages for local development testing.
 * 
 * Usage:
 *   php maintenance/run.php maintenance/gb_api_scripts/generate_sample_characters.php
 * 
 * Then import with:
 *   php maintenance/importDump.php < maintenance/gb_api_scripts/import_xml/sample_characters.xml
 */

require_once(__DIR__.'/libs/common.php');

class GenerateSampleCharacters extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates sample Character pages for local development");
    }

    public function execute()
    {
        $characters = $this->getSampleCharacters();
        
        $data = [];
        foreach ($characters as $char) {
            $data[] = [
                'title' => 'Characters/' . $char['page_name'],
                'namespace' => $this->namespaces['page'],
                'description' => $this->formatCharacterPage($char)
            ];
        }

        $this->createXML('sample_characters.xml', $data);
    }

    /**
     * Format a character page in the expected wikitext format
     */
    private function formatCharacterPage(array $char): string
    {
        $text = "{{Character\n";
        $text .= "| Name={$char['name']}\n";
        $text .= "| Guid={$char['guid']}\n";
        
        if (!empty($char['aliases'])) {
            $text .= "| Aliases={$char['aliases']}\n";
        }
        if (!empty($char['deck'])) {
            $text .= "| Deck={$char['deck']}\n";
        }
        if (!empty($char['real_name'])) {
            $text .= "| RealName={$char['real_name']}\n";
        }
        if (!empty($char['gender'])) {
            $text .= "| Gender={$char['gender']}\n";
        }
        if (!empty($char['birthday'])) {
            $text .= "| Birthday={$char['birthday']}\n";
        }
        if (!empty($char['franchises'])) {
            $text .= "| Franchises={$char['franchises']}\n";
        }
        if (!empty($char['games'])) {
            $text .= "| Games={$char['games']}\n";
        }
        if (!empty($char['friends'])) {
            $text .= "| Friends={$char['friends']}\n";
        }
        if (!empty($char['enemies'])) {
            $text .= "| Enemies={$char['enemies']}\n";
        }
        if (!empty($char['concepts'])) {
            $text .= "| Concepts={$char['concepts']}\n";
        }
        if (!empty($char['locations'])) {
            $text .= "| Locations={$char['locations']}\n";
        }
        if (!empty($char['objects'])) {
            $text .= "| Objects={$char['objects']}\n";
        }
        if (!empty($char['people'])) {
            $text .= "| People={$char['people']}\n";
        }
        
        $text .= "}}\n";
        
        // Add image data div if present
        if (!empty($char['image_data'])) {
            $text .= "<div id='imageData' data-json='" . htmlspecialchars($char['image_data'], ENT_QUOTES) . "' />\n";
        }
        
        // Add description content
        if (!empty($char['description'])) {
            $text .= "\n" . $char['description'];
        }
        
        return $text;
    }

    /**
     * Sample character data for testing
     */
    private function getSampleCharacters(): array
    {
        return [
            [
                'page_name' => 'Mario',
                'name' => 'Mario',
                'guid' => '3005-1',
                'aliases' => 'Jumpman,Mr. Video,Super Mario',
                'deck' => 'Nintendo\'s iconic plumber and mascot, Mario has been saving Princess Peach from Bowser for decades.',
                'real_name' => 'Mario Mario',
                'gender' => 'Male',
                'birthday' => '1981-07-09',
                'franchises' => 'Franchises/Super_Mario,Franchises/Mario_Kart,Franchises/Super_Smash_Bros',
                'games' => 'Games/Super_Mario_Bros,Games/Super_Mario_64,Games/Super_Mario_Odyssey,Games/Mario_Kart_8',
                'friends' => 'Characters/Luigi,Characters/Princess_Peach,Characters/Toad,Characters/Yoshi',
                'enemies' => 'Characters/Bowser,Characters/Wario,Characters/Waluigi',
                'concepts' => 'Concepts/Mascot,Concepts/Power_Ups',
                'locations' => 'Locations/Mushroom_Kingdom,Locations/Peach\'s_Castle',
                'objects' => 'Objects/Super_Mushroom,Objects/Fire_Flower,Objects/Super_Star',
                'people' => 'People/Charles_Martinet,People/Shigeru_Miyamoto',
                'image_data' => '{"infobox":{"file":"mario.jpg","path":"0/01/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Mario''' is the main protagonist of Nintendo's ''Super Mario'' franchise and the company's primary mascot.\n\n== History ==\nMario first appeared in the arcade game ''Donkey Kong'' (1981) as \"Jumpman,\" a carpenter trying to rescue his girlfriend from a giant ape. He was later renamed Mario and reimagined as a plumber.\n\n== Appearances ==\nMario has appeared in over 200 video games since his creation, making him one of the most prolific video game characters of all time."
            ],
            [
                'page_name' => 'Link',
                'name' => 'Link',
                'guid' => '3005-2',
                'aliases' => 'Hero of Time,Hero of Winds,Champion',
                'deck' => 'The legendary hero of Hyrule who repeatedly saves Princess Zelda and defeats the evil Ganon.',
                'gender' => 'Male',
                'franchises' => 'Franchises/The_Legend_of_Zelda,Franchises/Super_Smash_Bros',
                'games' => 'Games/The_Legend_of_Zelda_Ocarina_of_Time,Games/The_Legend_of_Zelda_Breath_of_the_Wild,Games/The_Legend_of_Zelda_Tears_of_the_Kingdom',
                'friends' => 'Characters/Princess_Zelda,Characters/Navi,Characters/Epona',
                'enemies' => 'Characters/Ganondorf,Characters/Ganon',
                'concepts' => 'Concepts/Silent_Protagonist,Concepts/Reincarnation',
                'locations' => 'Locations/Hyrule,Locations/Hyrule_Castle,Locations/Kokiri_Forest',
                'objects' => 'Objects/Master_Sword,Objects/Hylian_Shield,Objects/Triforce',
                'image_data' => '{"infobox":{"file":"link.jpg","path":"0/02/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Link''' is the main protagonist of Nintendo's ''The Legend of Zelda'' series.\n\n== Overview ==\nLink is typically depicted as a young Hylian man dressed in a green tunic and cap. Throughout the series, various incarnations of Link have appeared, each serving as the chosen hero destined to defeat evil.\n\n== The Triforce of Courage ==\nLink is the bearer of the Triforce of Courage, one of three golden triangles that together grant wishes to those who touch them."
            ],
            [
                'page_name' => 'Master_Chief',
                'name' => 'Master Chief',
                'guid' => '3005-3',
                'aliases' => 'John-117,Sierra-117,Chief',
                'deck' => 'The legendary Spartan-II super-soldier and primary protagonist of the Halo series.',
                'real_name' => 'John',
                'gender' => 'Male',
                'birthday' => '2511-03-07',
                'franchises' => 'Franchises/Halo',
                'games' => 'Games/Halo_Combat_Evolved,Games/Halo_2,Games/Halo_3,Games/Halo_Infinite',
                'friends' => 'Characters/Cortana,Characters/Sergeant_Johnson,Characters/Arbiter',
                'enemies' => 'Characters/Gravemind,Characters/343_Guilty_Spark',
                'concepts' => 'Concepts/Super_Soldier,Concepts/Power_Armor',
                'locations' => 'Locations/Reach,Locations/Installation_04',
                'objects' => 'Objects/MJOLNIR_Armor,Objects/Energy_Sword',
                'people' => 'People/Steve_Downes',
                'image_data' => '{"infobox":{"file":"masterchief.jpg","path":"0/03/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Master Chief Petty Officer John-117''', more commonly known as '''Master Chief''', is the protagonist and main playable character of the ''Halo'' series.\n\n== Background ==\nJohn was abducted at age six and conscripted into the SPARTAN-II program, where he was augmented and trained to become humanity's greatest super-soldier.\n\n== Military Career ==\nMaster Chief has been instrumental in humanity's survival against the Covenant and later the Flood, earning legendary status throughout the UNSC."
            ],
            [
                'page_name' => 'Kratos',
                'name' => 'Kratos',
                'guid' => '3005-4',
                'aliases' => 'Ghost of Sparta,God of War',
                'deck' => 'A Spartan warrior who became the Greek God of War before starting a new life in Norse mythology.',
                'gender' => 'Male',
                'franchises' => 'Franchises/God_of_War',
                'games' => 'Games/God_of_War,Games/God_of_War_II,Games/God_of_War_2018,Games/God_of_War_Ragnarok',
                'friends' => 'Characters/Atreus,Characters/Mimir,Characters/Freya',
                'enemies' => 'Characters/Zeus,Characters/Ares,Characters/Baldur,Characters/Odin',
                'concepts' => 'Concepts/Anti_Hero,Concepts/Revenge',
                'locations' => 'Locations/Sparta,Locations/Mount_Olympus,Locations/Midgard',
                'objects' => 'Objects/Blades_of_Chaos,Objects/Leviathan_Axe',
                'people' => 'People/Christopher_Judge,People/Terrence_C_Carson',
                'image_data' => '{"infobox":{"file":"kratos.jpg","path":"0/04/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Kratos''' is the main protagonist of the ''God of War'' video game series.\n\n== Greek Era ==\nOriginally a Spartan warrior, Kratos served Ares until he was tricked into killing his own family. He eventually killed Ares and took his place as the God of War.\n\n== Norse Era ==\nAfter destroying Olympus, Kratos relocated to Midgard where he raised his son Atreus while attempting to leave his violent past behind."
            ],
            [
                'page_name' => 'Solid_Snake',
                'name' => 'Solid Snake',
                'guid' => '3005-5',
                'aliases' => 'David,Old Snake,Iroquois Pliskin',
                'deck' => 'A legendary soldier and spy who has repeatedly saved the world from nuclear annihilation.',
                'real_name' => 'David',
                'gender' => 'Male',
                'birthday' => '1972-01-01',
                'franchises' => 'Franchises/Metal_Gear,Franchises/Super_Smash_Bros',
                'games' => 'Games/Metal_Gear_Solid,Games/Metal_Gear_Solid_2_Sons_of_Liberty,Games/Metal_Gear_Solid_4_Guns_of_the_Patriots',
                'friends' => 'Characters/Otacon,Characters/Meryl_Silverburgh,Characters/Roy_Campbell',
                'enemies' => 'Characters/Liquid_Snake,Characters/Revolver_Ocelot,Characters/Big_Boss',
                'concepts' => 'Concepts/Cloning,Concepts/Stealth,Concepts/Nuclear_Weapons',
                'locations' => 'Locations/Shadow_Moses',
                'objects' => 'Objects/Cardboard_Box,Objects/SOCOM_Pistol',
                'people' => 'People/David_Hayter,People/Hideo_Kojima',
                'image_data' => '{"infobox":{"file":"snake.jpg","path":"0/05/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Solid Snake''' is the primary protagonist of the ''Metal Gear'' series, created by Hideo Kojima.\n\n== Origins ==\nSolid Snake is a clone of the legendary soldier Big Boss, created as part of the Les Enfants Terribles project.\n\n== Career ==\nSnake is a former Green Beret and member of the high-tech special forces unit FOXHOUND. He has single-handedly stopped multiple nuclear-equipped walking battle tanks known as Metal Gears."
            ],
            [
                'page_name' => 'Samus_Aran',
                'name' => 'Samus Aran',
                'guid' => '3005-6',
                'aliases' => 'The Hunter',
                'deck' => 'An intergalactic bounty hunter and one of gaming\'s first female protagonists.',
                'gender' => 'Female',
                'franchises' => 'Franchises/Metroid,Franchises/Super_Smash_Bros',
                'games' => 'Games/Metroid,Games/Super_Metroid,Games/Metroid_Prime,Games/Metroid_Dread',
                'friends' => 'Characters/Adam_Malkovich',
                'enemies' => 'Characters/Ridley,Characters/Mother_Brain,Characters/Dark_Samus',
                'concepts' => 'Concepts/Bounty_Hunter,Concepts/Power_Armor',
                'locations' => 'Locations/Zebes,Locations/SR388,Locations/Tallon_IV',
                'objects' => 'Objects/Power_Suit,Objects/Arm_Cannon,Objects/Morph_Ball',
                'image_data' => '{"infobox":{"file":"samus.jpg","path":"0/06/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Samus Aran''' is the protagonist of the ''Metroid'' series and one of the earliest female protagonists in video game history.\n\n== Background ==\nSamus was orphaned when Space Pirates attacked her home colony. She was raised by the Chozo, an ancient bird-like race who trained her as a warrior and gave her the iconic Power Suit.\n\n== Career ==\nAs an intergalactic bounty hunter, Samus has repeatedly thwarted the Space Pirates and destroyed numerous Metroid specimens to prevent them from being weaponized."
            ],
            [
                'page_name' => 'Cloud_Strife',
                'name' => 'Cloud Strife',
                'guid' => '3005-7',
                'aliases' => 'Spiky',
                'deck' => 'A former SOLDIER turned mercenary who joins the eco-terrorist group AVALANCHE.',
                'gender' => 'Male',
                'franchises' => 'Franchises/Final_Fantasy,Franchises/Kingdom_Hearts',
                'games' => 'Games/Final_Fantasy_VII,Games/Final_Fantasy_VII_Remake,Games/Kingdom_Hearts',
                'friends' => 'Characters/Tifa_Lockhart,Characters/Aerith_Gainsborough,Characters/Barret_Wallace',
                'enemies' => 'Characters/Sephiroth,Characters/Shinra',
                'concepts' => 'Concepts/Amnesia,Concepts/JRPG',
                'locations' => 'Locations/Midgar,Locations/Nibelheim',
                'objects' => 'Objects/Buster_Sword,Objects/Materia',
                'people' => 'People/Takahiro_Sakurai,People/Steve_Burton',
                'image_data' => '{"infobox":{"file":"cloud.jpg","path":"0/07/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Cloud Strife''' is the main protagonist of ''Final Fantasy VII'' and one of the most iconic characters in gaming.\n\n== Background ==\nCloud presents himself as an ex-member of SOLDIER, Shinra's elite military force. However, his memories are fragmented and unreliable, hiding a more complex past.\n\n== AVALANCHE ==\nCloud joins the eco-terrorist group AVALANCHE as a mercenary, initially only interested in payment. He eventually becomes committed to stopping Shinra and pursuing his nemesis Sephiroth."
            ],
            [
                'page_name' => 'Geralt_of_Rivia',
                'name' => 'Geralt of Rivia',
                'guid' => '3005-8',
                'aliases' => 'The White Wolf,Butcher of Blaviken,Gwynbleidd',
                'deck' => 'A professional monster hunter known as a Witcher, mutated and trained from childhood.',
                'gender' => 'Male',
                'franchises' => 'Franchises/The_Witcher',
                'games' => 'Games/The_Witcher,Games/The_Witcher_2_Assassins_of_Kings,Games/The_Witcher_3_Wild_Hunt',
                'friends' => 'Characters/Yennefer,Characters/Triss_Merigold,Characters/Dandelion',
                'enemies' => 'Characters/Wild_Hunt,Characters/Eredin',
                'concepts' => 'Concepts/Monster_Hunter,Concepts/Mutant',
                'locations' => 'Locations/Kaer_Morhen,Locations/Novigrad,Locations/Skellige',
                'objects' => 'Objects/Silver_Sword,Objects/Steel_Sword',
                'people' => 'People/Doug_Cockle',
                'image_data' => '{"infobox":{"file":"geralt.jpg","path":"0/08/","mime":"image/jpeg","sizes":"scale_small,square_medium"},"background":{}}',
                'description' => "'''Geralt of Rivia''' is the protagonist of ''The Witcher'' series, based on the novels by Andrzej Sapkowski.\n\n== The Witchers ==\nWitchers are professional monster hunters who undergo dangerous mutations and rigorous training from childhood. Geralt is one of the last remaining Witchers.\n\n== The White Wolf ==\nGeralt's distinctive white hair is a result of additional experimental mutations he survived as a child. He is known throughout the Continent as an exceptionally skilled and ethical Witcher."
            ],
        ];
    }
}

$maintClass = GenerateSampleCharacters::class;

require_once RUN_MAINTENANCE_IF_MAIN;
