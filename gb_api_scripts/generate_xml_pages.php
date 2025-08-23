<?php

require_once(__DIR__.'/common.php');

class GenerateXMLPages extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for pages used to setup content pages");
    }

    public function execute()
    {
        $data = [
            [
                'title' => 'Ratings/BBFC_12',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=BBFC: 12
|Image=BBFC 12.png
|Caption=Logo for BBFC: 12
|Explanation=Suitable for 12 years and over
}}
Content contain material that is not generally suitable for children aged under 12.
MARKUP,
            ],
            [
                'title' => 'Ratings/BBFC_15',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=BBFC: 15
|Image=BBFC 15.png
|Caption=Logo for BBFC: 15
|Explanation=Suitable only for 15 years and over
}}
Content contain material that is not generally suitable for children aged under 15.
MARKUP,
            ],
            [
                'title' => 'Ratings/BBFC_18',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=BBFC: 18
|Image=BBFC 18.png
|Caption=Logo for BBFC: 18
|Explanation=Suitable only for adults
}}
Content contain material that is generally suitable for adults over the age of 18.
MARKUP,
            ],
            [
                'title' => 'Ratings/BBFC_PG',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=BBFC: PG
|Image=BBFC PG.png
|Caption=Logo for BBFC: PG
|Explanation=Parental Guidance
}}
PG-rated content is suitable for general viewing. A PG should generally not unsettle a child aged around eight, although parents and caregivers should be aware that some scenes may be unsuitable for more sensitive children.
MARKUP,
            ],
            [
                'title' => 'Ratings/BBFC_U',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=BBFC: U
|Image=BBFC U.png
|Caption=Logo for BBFC: U
|Explanation=Suitable for all
}}
U-rated content is suitable for audiences of all ages, although not all U-rated content is aimed at children.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_15',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: 15+
|Image=CERO 15.png
|Caption=Logo for CERO: 15+
|Explanation=Ages 15 and up
}}
Expression and content suitable only to 15-year-olds and above are included in the game.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_18',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: 18+
|Image=CERO 18.png
|Caption=Logo for CERO: 18+
|Explanation=Ages 18 and up
}}
Contains adult material. Expression and content suitable only to 18-year-olds and above are included in the game.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_A',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: A
|Image=CERO A.png
|Caption=Logo for CERO A
|Explanation=All Ages
}}
Expressions and content subjected to age-specific limitation are not included in the game, thereby being suitable for all ages. All games that used to be rated All go into this category.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_All_Ages',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: All Ages
|Image=CERO: All Ages.png
|Caption=Logo for CERO: All Ages
|Explanation=All Ages
}}
Expressions and content subjected to age-specific limitation are not included in the game, thereby being suitable for all ages.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_B',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: B
|Image=CERO B.png
|Caption=Logo for CERO B
|Explanation=Ages 12 and up
}}
Expression and content suitable only to 12-year-olds and above are included in the game. All games that used to be rated 12 go into this category.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_C',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: C
|Image=CERO C.png
|Caption=Logo for CERO C
|Explanation=Ages 15 and up
}}
Expression and content suitable only to 15-year-olds and above are included in the game. All games that used to be rated 15 go into this category.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_D',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: D
|Image=CERO D.png
|Caption=Logo for CERO D
|Explanation=Ages 17 and up
}}
Contains adult material. Expression and content suitable only to 17-year-olds and above are included in the game. Some games that used to be rated 18 go into this category.
MARKUP,
            ],
            [
                'title' => 'Ratings/CERO_Z',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=CERO: Z
|Image=CERO Z.png
|Caption=Logo for CERO Z
|Explanation=Ages 18 and up only
}}
Contains strong adult material. It is illegal for anyone under 18 to buy video games with this rating. Expression and content suitable only to 18-year-olds and above are included in the game. Some games that used to be rated 18 go into this category.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_AO',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: AO
|Image=ESRB AO.png
|Caption=Logo for ESRB: AO
|Explanation=Adults Only 18+
}}
Content that the ESRB believes is suitable for ages 18 and over.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_E',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: E
|Image=ESRB E.png
|Caption=Logo for ESRB: E
|Explanation=Everyone
}}
Content is generally suitable for all ages. May contain minimal cartoon, fantasy or mild violence, and/or infrequent use of mild language.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_E10',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: E10+
|Image=ESRB E10.png
|Caption=Logo for ESRB: E10+
|Explanation=Everyone 10+
}}
Content suitable for ages 10 and over, including a larger amount of cartoon, fantasy, or mild violence than the "E" rating can accommodate, mild to moderate use of profane language, and minimal suggestive themes.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_EC',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: EC
|Image=ESRB EC.png
|Caption=Logo for ESRB: EC
|Explanation=Early Childhood
}}
Content that parents would find objectionable to a preschool audience.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_K_A',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: K-A
|Image=ESRB K-A.png
|Caption=Logo for ESRB: K-A
|Explanation=Kids to Adults
}}
Content suitable for everyone. Actively used between 1994 to 1998 before being replaced by [[Ratings/ESRB_E|ESRB: E]].
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_M',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: M
|Image=ESRB M.png
|Caption=Logo for ESRB: M
|Explanation=Mature 17+
}}
Content is generally suitable for ages 17 and up. May contain intense violence, blood and gore, sexual content and/or strong language.
MARKUP,
            ],
            [
                'title' => 'Ratings/ESRB_T',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=ESRB: T
|Image=ESRB T.png
|Caption=Logo for ESRB: T
|Explanation=Teen
}}
Content suitable for ages 13 and over, including aggressive depictions of violence with minimal blood, moderate suggestive themes, crude humor, and stronger use of profane language.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_G',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: G
|Image=OFLC G.png
|Caption=Logo for OFLC: G
|Explanation=Suitable for general audiences
}}
Content should have very low levels of things like frightening scenes. However, not all G level content are intended for family audiences and it is always a good idea to look at reviews and plot information before letting children play the games.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_GB',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: GB+
|Image=OFLC GB.png
|Caption=Logo for OFLC: GB+
|Explanation=Green band; approved for all audiences
}}
Indicates that the trailer has been approved for all audiences by the MPAA.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_M',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: M
|Image=OFLC M.png
|Caption=Logo for OFLC: M
|Explanation=Suitable for (but not restricted to) mature audiences 16 years and up
}}
Games with an M label are more suitable for mature audiences.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_M15',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: M15+
|Image=OFLC M15.png
|Caption=Logo for OFLC: M15+
|Explanation=Mature
}}
Despite the title, material classified M15+ is not recommended for people under 15 years of age. Nonetheless, there are still no legal restrictions thus any age is allowed to access these titles.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_MA15',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: MA15+
|Image=OFLC MA15.png
|Caption=Logo for OFLC: MA15+
|Explanation=Mature Accompanied
}}
Content is considered unsuitable for exhibition by persons under the age of 15. Persons under this age may only legally purchase or exhibit MA15+ rated content under the supervision of an adult guardian. This is a legally restricted category.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_PG',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: PG
|Image=OFLC PG.png
|Caption=Logo for OFLC: PG
|Explanation=Parental guidance recommended for younger viewers
}}
The PG label means guidance from a parent or guardian is recommended for younger viewers. It is important to remember that PG games can be aimed at an adult audience and to be aware of the content of a game for children.
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_R13',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: R13
|Image=OFLC R13.png
|Caption=Logo for OFLC: R13
|Explanation=Restricted to persons 13 years of age and over
}}
If something has one of these labels it can only be supplied to people of and over the age shown on the label. A parent or shop is breaking the law if they supply an age-restricted item to someone who is not legally allowed to access it. 
MARKUP,
            ],
            [
                'title' => 'Ratings/OFLC_R18',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=OFLC: R18+
|Image=OFLC R18.png
|Caption=Logo for OFLC: R18+
|Explanation=Restricted to 18 and over
}}
People under 18 may not buy, rent or exhibit these films. Games rated this are banned from Australia.
MARKUP,
            ],
            [
                'title' => 'Ratings/PEGI_12',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=PEGI: 12
|Image=PEGI 12.png
|Caption=Logo for PEGI: 12
|Explanation=Suitable for 12 and over
}}
Games that show violence of a slightly more graphic nature towards fantasy characters or non-realistic violence towards human-like characters would fall in this age category. Sexual innuendo or sexual posturing can be present, while any bad language in this category must be mild.
MARKUP,
            ],
            [
                'title' => 'Ratings/PEGI_16',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=PEGI: 16+
|Image=PEGI 16.png
|Caption=Logo for PEGI: 16+
|Explanation=Suitable for ages 16 and over
}}
Rating is applied once the depiction of violence (or sexual activity) reaches a stage that looks the same as would be expected in real life. The use of bad language in games with a PEGI 16 rating can be more extreme, while the use of tobacco, alcohol or illegal drugs can also be present.
MARKUP,
            ],
            [
                'title' => 'Ratings/PEGI_18',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=PEGI: 18+
|Image=PEGI 18.png
|Caption=Logo for PEGI: 18+
|Explanation=Suitable for adults only
}}
The adult classification is applied when the level of violence reaches a stage where it becomes a depiction of gross violence, apparently motiveless killing, or violence towards defenceless characters. The glamorisation of the use of illegal drugs and of the simulation of gambling, and explicit sexual activity should also fall into this age category. 
MARKUP,
            ],    
            [
                'title' => 'Ratings/PEGI_3',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=PEGI: 3+
|Image=PEGI 3.png
|Caption=Logo for PEGI: 3
|Explanation=Suitable for all ages
}}
Content of games is considered suitable for all age groups. The game should not contain any sounds or pictures that are likely to frighten young children. A very mild form of violence (in a comical context or a childlike setting) is acceptable. No bad language should be heard.
MARKUP,
            ],
            [
                'title' => 'Ratings/PEGI_7',
                'namespace' => $this->namespaces['page'],
                'description' => <<<MARKUP
{{Rating
|Name=PEGI: 7+
|Image=PEGI 7.png
|Caption=Logo for PEGI: 7+
|Explanation=Suitable for ages 7 and over
}}
Content with scenes or sounds that can possibly be frightening to younger children should fall in this category. Very mild forms of violence (implied, non-detailed, or non-realistic violence) are acceptable for a game with a PEGI 7 rating.
MARKUP,
            ],
        ];

        $this->createXML('pages.xml', $data);
    }
}

$maintClass = GenerateXMLPages::class;

require_once RUN_MAINTENANCE_IF_MAIN; 