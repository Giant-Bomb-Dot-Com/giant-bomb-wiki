# Game Page Templates - MediaWiki Way

This directory contains the wiki templates needed to render game pages server-side using MediaWiki's native template system instead of custom PHP interception.

## Overview

The new approach follows the "MediaWiki way":
1. **Templates** define the page structure and layout using wikitext
2. **CSS** handles all styling
3. **SMW queries** pull related data for the sidebar
4. **Standard MediaWiki rendering** handles everything else

## Template Files

### `Template_Game.wikitext`
The main game page template. This replaces the old template that only output a basic infobox.

**What it does:**
- Sets all SMW properties (same as before)
- Outputs the full page layout: hero, tabs, content area
- Opens the wiki content div for user-editable content

### `Template_GameEnd.wikitext`
Closes the game page layout and adds the sidebar.

**Usage:** Place at the end of game page content.

### `Template_GameSidebar.wikitext`
Renders the sidebar with all game details using SMW queries.

**Sections rendered:**
- Game details (name, release date, platforms, developers, etc.)
- Similar games
- Related content tabs (Characters, Locations, Concepts, Objects)

## How to Import

### Option 1: Manual Wiki Import
1. Go to `Special:Import` in your wiki
2. Upload each `.wikitext` file as a new template

### Option 2: Use Import Script
```bash
php maintenance/importTextFile.php --overwrite \
  --title="Template:Game" \
  skins/GiantBomb/templates/wiki/Template_Game.wikitext
```

Repeat for each template file.

### Option 3: Update Generator
Modify `gb_api_scripts/generate_xml_templates.php` to output the new template structure, then re-run the XML generation and import.

## Example Game Page Structure

```wikitext
{{Game
| Name=Doom
| Guid=3030-2391
| Image=Doom_cover.jpg
| Deck=The groundbreaking first-person shooter that defined a genre.
| ReleaseDate=1993-12-10
| Developers=id Software
| Publishers=GT Interactive
| Platforms=PC, DOS, PlayStation, Saturn
| Genres=First-Person Shooter
| Themes=Sci-Fi, Horror
}}

== Overview ==
''Doom'' is a first-person shooter developed by [[Companies/id Software|id Software]]...

== Gameplay ==
The player navigates through maze-like levels...

== Story ==
The game takes place on the moons of Mars...

{{GameEnd}}
```

## CSS Styles

The styles are in `skins/GiantBomb/resources/css/game-wiki.css`:
- `.gb-game-page` - Main page container
- `.gb-game-hero` - Hero section with cover, title, platforms
- `.gb-game-tabs` - Tab navigation
- `.gb-game-content` - Two-column layout
- `.gb-game-main` - Main content area (left)
- `.gb-game-sidebar` - Sidebar (right)
- `.gb-sidebar-section` - Sidebar section blocks

## Configuration

To switch between custom PHP rendering and MediaWiki templates, edit:

`skins/GiantBomb/includes/GiantBombTemplate.php`

```php
// Set to true for custom PHP rendering
// Set to false for MediaWiki template rendering (recommended)
$useCustomGamePage = false;
```

## Testing

1. Start the wiki: `docker-compose up -d`
2. Navigate to a game page: `http://localhost:8080/wiki/Games/Doom`
3. The page should render using the new template system
4. Verify:
   - Hero section shows cover, title, platforms, deck
   - Tab navigation is visible
   - Content area has two columns
   - Sidebar shows game details from SMW
   - Edit links work correctly

## Benefits of This Approach

1. **Standard MediaWiki** - Uses native template/CSS system
2. **Editable** - All content editable through wiki interface
3. **Cacheable** - MediaWiki handles caching automatically
4. **Extensible** - Easy to modify templates without PHP changes
5. **SEO-friendly** - Server-side rendered content
6. **Form-compatible** - Works with PageForms for structured editing

