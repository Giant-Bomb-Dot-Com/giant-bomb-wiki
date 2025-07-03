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
    - Select "Use this skin as default" on the skin you wish to use
    - Select every choice under Extensions
    - Select Enable File Uploads
    - Click continue at bottom of page
    - Click continue again
    - One more time
- Now you will get a LocalSettings.php file to your Downloads folder. Move that into the /config folder.
- Before doing anything else, run `docker compose restart`.
- On this first full startup it will take a bit longer as it has to patch the DB, you can see this if you look at the container log. (Approx 10 seconds)
- You should now be able to access the wiki at http://localhost:8080/ and you should see the Gamepress skin enabled.
- You can visit http://localhost:8080/index.php/Special:Version to see the loaded features.
  * Skins
    - GiantBomb
    - Vector
  * Extensions
    - Seemantic Extra Special Properties
    - Semantic MediaWiki
    - Semantic Result Formats
    - Semantic Scribunto
    - CodeEditor    
    - WikiEditor
    - Scribunto
    - TemplateData
    - TemplateStyles
    - TemplateStylesExtender

## Skins

- TODO: Learn about these!
- You can disable/enable skins by editing the LocalSettings.php file. See https://www.mediawiki.org/wiki/Manual:LocalSettings.php
* Since the base skins volume is overwritten there is a temporary exemption on the Vector skin so it can be selected. Remove the line ``- /var/www/html/skins/Vector`` in docker-compose once the GB skin is more usable.

## SemanticMediaWiki
- Add more notes
- Can add SMW attributes test by going to: http://localhost:8080/index.php?title=The_Legend_of_Zelda:_Twilight_Princess and creating page with the following:
  ```
  {{#set:
  Has Name=Pitfall
  |Has Platform=Xbox
  |Has Platform=Playstation
  |Has Platform=iPhone
  |Has Release=Aug 09, 2012
  }}
  ```

  then go to: http://localhost:8080/index.php/Games and create with the following:
  ```
  {{#ask:
  [[Has Platform::Xbox]]
  |mainlabel=Game
  |?Has Release=Release Date 
  }}
  ```

## TODO's

- ~~We should probably remove a lot of the stuff in this repo that could be generated/downloaded from MediaWiki via cli. Probably.~~
- Get started on proof of concept approaches to theming the wiki
- Get started building out some of the complex relationships between categories like we have on the current wiki
