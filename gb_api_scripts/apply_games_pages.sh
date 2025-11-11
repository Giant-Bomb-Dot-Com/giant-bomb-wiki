#!/usr/bin/env sh
set -euo pipefail

# Creates/updates Template:GameCard and the Games index page in MediaWiki
# using maintenance/edit.php. No XML import required.

MW_PATH=${MW_PATH:-/var/www/html}
PHP_BIN=${PHP_BIN:-php}

tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT INT TERM

gamecard_file="$tmp_dir/gamecard.wikitext"
games_file="$tmp_dir/games.wikitext"

cat > "$gamecard_file" <<'WIKI'
<includeonly>
<div class="gb-card">
  <div class="gb-card__image">
    {{#if:{{{Image|}}}|[[File:{{{Image}}}|200px|link={{{Page}}}]]}}
  </div>
  <div class="gb-card__body">
    <div class="gb-card__title">[[{{{Page}}}|{{{Name|}}}]]</div>
    <div class="gb-card__meta">{{#if:{{{ReleaseYear|}}}|{{{ReleaseYear}}}}}</div>
    <div class="gb-card__deck">{{{Deck|}}}</div>
  </div>
  <div style="clear:both"></div>
</div>
</includeonly>
<noinclude>Card used by [[Games]] index.</noinclude>
WIKI

cat > "$games_file" <<'WIKI'
<noinclude>This page lists all games.</noinclude>
{{#ask:
 [[Category:Games]]
 | ?Has name=Name
 | ?Has image=Image
 | ?Has deck=Deck
 | ?Has guid=Guid
 | ?Has release date#-F[Y]=ReleaseYear
 | sort=Has name
 | order=asc
 | format=template
 | template=GameCard
 | named args=yes
 | mainlabel=Page
 | limit=24
 | searchlabel=More games...
}}
WIKI

"$PHP_BIN" "$MW_PATH/maintenance/edit.php" --summary "Init GameCard template" "Template:GameCard" "$gamecard_file"
"$PHP_BIN" "$MW_PATH/maintenance/edit.php" --summary "Init Games index" "Games" "$games_file"

echo "Applied Template:GameCard and Games index page."

