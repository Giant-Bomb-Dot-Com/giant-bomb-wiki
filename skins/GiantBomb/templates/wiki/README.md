# Game Page Templates

Wikitext templates that render game pages server-side using MediaWiki's native template system.

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

## Files

| Template                        | Purpose                                                                |
| ------------------------------- | ---------------------------------------------------------------------- |
| `Template_Game.wikitext`        | Opens game page layout (hero, tabs, content area), sets SMW properties |
| `Template_GameEnd.wikitext`     | Closes layout and renders sidebar                                      |
| `Template_GameSidebar.wikitext` | Sidebar with game details via SMW queries                              |
| `Template_StripPrefix.wikitext` | Helper for stripping namespace prefixes                                |

## Page Structure

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

## Data Model

SMW properties set by `{{Game}}`:

- `Has name`, `Has guid`, `Has deck`, `Has release date`
- `Has platforms`, `Has developers`, `Has publishers` (multi-value)
- `Has genres`, `Has themes`, `Has franchise` (multi-value)
- `Has characters`, `Has concepts`, `Has locations`, `Has objects` (multi-value)

Values use wiki page paths (e.g., `Companies/id_Software`) which link to their respective pages.

## Styles

CSS: `skins/GiantBomb/resources/css/game-wiki.css`

Key classes:

- `.gb-game-page` - Main container
- `.gb-game-hero` - Hero section
- `.gb-game-content` - Two-column grid
- `.gb-game-sidebar` - Right sidebar

## Notes

- Templates are auto-imported from data dumps
- `{{GameEnd}}` is auto-appended if missing (via PHP hook)
- Hero images loaded client-side from embedded JSON in page content
