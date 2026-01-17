# Wiki Page Templates

Wikitext templates that render wiki pages server-side using MediaWiki's native template system.

## The MediaWiki Way

This approach follows standard MediaWiki patterns:

1. **Templates** define page structure and layout using wikitext
2. **CSS** handles all styling
3. **SMW queries** pull related data for the sidebar
4. **Standard MediaWiki rendering** handles caching, parsing, and output

Benefits:

- Editable through wiki interface
- Cacheable via MediaWiki's parser cache
- Extensible without PHP changes
- SEO-friendly server-side rendering
- Works with PageForms for structured editing

## Content Type Templates

### Game

| Template                        | Purpose                                                                |
| ------------------------------- | ---------------------------------------------------------------------- |
| `Template_Game.wikitext`        | Opens game page layout (hero, tabs, content area), sets SMW properties |
| `Template_GameEnd.wikitext`     | Closes layout and renders sidebar                                      |
| `Template_GameSidebar.wikitext` | Sidebar with game details via SMW queries                              |

CSS: `skins/GiantBomb/resources/css/game-wiki.css`

### Character

| Template                             | Purpose                                          |
| ------------------------------------ | ------------------------------------------------ |
| `Template_Character.wikitext`        | Opens character page layout, sets SMW properties |
| `Template_CharacterEnd.wikitext`     | Closes layout and renders sidebar                |
| `Template_CharacterSidebar.wikitext` | Sidebar with character details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/character-wiki.css`

### Franchise

| Template                               | Purpose                                          |
| -------------------------------------- | ------------------------------------------------ |
| `Template_Franchise.wikitext`          | Opens franchise page layout, sets SMW properties |
| `Template_FranchiseEnd.wikitext`       | Closes layout, shows games list, renders sidebar |
| `Template_FranchiseSidebar.wikitext`   | Sidebar with franchise details via SMW queries   |
| `Template_FranchiseFirstGame.wikitext` | Helper: renders first game in franchise          |
| `Template_FranchiseGameItem.wikitext`  | Helper: renders individual game in games list    |

CSS: `skins/GiantBomb/resources/css/franchise-wiki.css`

### Company

| Template                           | Purpose                                        |
| ---------------------------------- | ---------------------------------------------- |
| `Template_Company.wikitext`        | Opens company page layout, sets SMW properties |
| `Template_CompanyEnd.wikitext`     | Closes layout and renders sidebar              |
| `Template_CompanySidebar.wikitext` | Sidebar with company details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/company-wiki.css`

### Concept

| Template                           | Purpose                                        |
| ---------------------------------- | ---------------------------------------------- |
| `Template_Concept.wikitext`        | Opens concept page layout, sets SMW properties |
| `Template_ConceptEnd.wikitext`     | Closes layout and renders sidebar              |
| `Template_ConceptSidebar.wikitext` | Sidebar with concept details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/concept-wiki.css`

### Location

| Template                            | Purpose                                         |
| ----------------------------------- | ----------------------------------------------- |
| `Template_Location.wikitext`        | Opens location page layout, sets SMW properties |
| `Template_LocationEnd.wikitext`     | Closes layout and renders sidebar               |
| `Template_LocationSidebar.wikitext` | Sidebar with location details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/location-wiki.css`

### Person

| Template                          | Purpose                                       |
| --------------------------------- | --------------------------------------------- |
| `Template_Person.wikitext`        | Opens person page layout, sets SMW properties |
| `Template_PersonEnd.wikitext`     | Closes layout and renders sidebar             |
| `Template_PersonSidebar.wikitext` | Sidebar with person details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/person-wiki.css`

### Platform

| Template                            | Purpose                                         |
| ----------------------------------- | ----------------------------------------------- |
| `Template_Platform.wikitext`        | Opens platform page layout, sets SMW properties |
| `Template_PlatformEnd.wikitext`     | Closes layout and renders sidebar               |
| `Template_PlatformSidebar.wikitext` | Sidebar with platform details via SMW queries   |

CSS: `skins/GiantBomb/resources/css/platform-wiki.css`

### Shared Helpers

| Template                               | Purpose                                    |
| -------------------------------------- | ------------------------------------------ |
| `Template_SidebarListItem.wikitext`    | Helper for rendering sidebar list items    |
| `Template_SidebarRelatedItem.wikitext` | Helper for rendering related content items |
| `Template_StripPrefix.wikitext`        | Helper for stripping namespace prefixes    |

## Page Structure Pattern

All content types follow the same structure:

```wikitext
{{ContentType
| Name = Example Name
| Guid = 3030-1234
| Deck = Short description...
| ... other properties ...
}}

== Overview ==
Your wiki content here...

== History ==
More content...

{{ContentTypeEnd}}
```

## Example: Game Page

```wikitext
{{Game
| Name=Doom
| Guid=3030-2391
| Deck=The groundbreaking FPS that defined a genre.
| ReleaseDate=1993-12-10
| Platforms=Platforms/PC, Platforms/PlayStation
| Developers=Companies/id_Software
| Publishers=Companies/GT_Interactive
| Genres=Genres/First-Person_Shooter
| Themes=Themes/Sci-Fi, Themes/Horror
| Characters=Characters/Doomguy
| Concepts=Concepts/Demons
}}

== Overview ==
Content here...

== Gameplay ==
More content...

{{GameEnd}}
```

## Example: Company Page

```wikitext
{{Company
| Name = Nintendo
| Guid = 3010-805
| Deck = Japanese multinational video game company...
| Abbreviation = Nintendo
| FoundedDate = 1889-09-23
| City = Kyoto
| Country = Japan
| Website = https://www.nintendo.com
| DevelopedGames = Games/Super_Mario_Bros, Games/The_Legend_of_Zelda
| PublishedGames = Games/Pokemon_Red_and_Blue
| People = People/Shigeru_Miyamoto
}}

== Overview ==
Wiki content here...

{{CompanyEnd}}
```

## SMW Properties

Each content type sets semantic properties that enable:

- Cross-linking between related content
- SMW queries in sidebars
- Category pages with filtered/sorted listings
- Search and discovery features

Values use wiki page paths (e.g., `Companies/id_Software`) which link to their respective pages.

## CSS Class Pattern

Each content type uses a consistent class naming pattern:

- `.gb-{type}-page` - Main container
- `.gb-{type}-hero` - Hero section with title/image/deck
- `.gb-{type}-tabs` - Tab navigation
- `.gb-{type}-content` - Two-column grid layout
- `.gb-{type}-main` - Main content area (left)
- `.gb-{type}-sidebar` - Sidebar (right)
- `.gb-{type}-wiki-content` - Wiki text content container
- `.gb-{type}-details` - Sidebar details list

## Notes

- Templates are auto-imported from data dumps via `import_templates/`
- `{{*End}}` templates are auto-appended if missing (via PHP hook)
- Hero images loaded client-side from embedded JSON in page content
- All templates support PageForms for structured editing
