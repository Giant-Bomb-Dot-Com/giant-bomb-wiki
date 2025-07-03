# The Giant Bomb Wiki

We should add stuff here.

## Running the wiki for the first time

- Have docker desktop installed and running
- Run `docker compose up -d` in the root of this repo.
- Go to http://localhost:8080/ in your browser. The installer will run automatically on first start.
  * Select options and hit continue until you reach the "MediaWiki 1.43.3 installation" page.  
  * The values you need for the database connection are in the docker-compose.yml file - "db" is your host, etc.
  * Hit continue on a few more pages until you need to name wiki.
  * Name the wiki and enter the username and password for the Administrator account
  * At the bottom of this page make sure to select "Ask me more questions." and hit continue
   - Choose "Authorized editors only"
   - "Creative Commons Attribution-NonCommercial-ShareAlike"
   - uncheck "Enable outbound email"
   - Select "Use this skin as default" on the skin
   - Select "Semantic MediaWiki" under Extensions
   - Click continue at bottom of page
   - Click continue again
   - One more time
- Now you will get a LocalSettings.php file to your Downloads folder. Move that into the /config folder.
- Before doing anything else, run `docker compose restart`.
- On this first full startup it will take a bit longer as it has to patch the DB, you can see this if you look at the container log. (Approx 10 seconds)
- You should now be able to access the wiki at http://localhost:8080/ and you should see the Gamepress skin enabled.

## Skins

- TODO: Learn about these!
- You can disable/enable skins by editing the LocalSettings.php file. See https://www.mediawiki.org/wiki/Manual:LocalSettings.php

## SemanticMediaWiki

- TODO: Add examples of usage

## TODO's

- ~~We should probably remove a lot of the stuff in this repo that could be generated/downloaded from MediaWiki via cli. Probably.~~
- Get started on proof of concept approaches to theming the wiki
- Get started building out some of the complex relationships between categories like we have on the current wiki
