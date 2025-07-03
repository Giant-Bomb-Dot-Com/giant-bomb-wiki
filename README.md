# The Giant Bomb Wiki

We should add stuff here.

## Running the wiki for the first time

- Have docker desktop installed and running
- Run `docker compose up -d` in the root of this repo.
- Go to http://localhost:8080/ in your browser. The installer will run automatically on first start.
- The values you need for the database connection are in the docker-compose.yml file - "db" is your host, etc.
- Make sure to check to install "Semantic MediaWiki"
- Make your admin account, and save the password!
- You will get to a screen that will save a LocalSettings.php file to your Downloads folder. Move that into the /config folder.
- Before doing anything else, run `docker compose restart`.
- You should now be able to access the wiki at http://localhost:8080/ and you should see the Giant Bomb skin enabled.
- See **"Skins"** for adding the Giant Bomb skin to your `LocalSettings.php` if needed.

## Skins

- You can disable/enable skins by editing the `LocalSettings.php` file. See https://www.mediawiki.org/wiki/Manual:LocalSettings.php
- To start working on the new Giant Bomb Skin add the following to your `LocalSettings.php` file:
  - `wfLoadSkin( 'GiantBomb' );`
  - `$wgDefaultSkin = "giantbomb";`
- Make sure your editor of choice is setup with [Prettier](https://prettier.io/docs/install) as a default formatter. We're relying on Prettier to enforce our [`.editorconfig`](https://editorconfig.org/) rules.

### Building Vue Components

- Create new Vue Components in `/skins/GiantBomb/resources/components` as `.js` files
  - See `/skins/GiantBomb/resources/components/VueExampleComponent.js` as an example
- New Vue Components need to be added to `skin.json` as a seperate Resource Module
  - See `skin.giantbomb.vueexamplecomponent` for example
- Components must then be loaded via the components object in `/skins/GiantBomb/resources/components/index.js`
- In any `.php` template use the attribute `data-vue-component=` on any DOM element
  - See `/skins/GiantBomb/includes/GiantBombTemplate.php` as an example
- Vue Component will be added to DOM as a child of that element
- Props are fully functional, see `VueExampleComponent.js` for example

## TODO's

- ~~We should probably remove a lot of the stuff in this repo that could be generated/downloaded from MediaWiki via cli. Probably.~~
- Get started on proof of concept approaches to theming the wiki
- Get started building out some of the complex relationships between categories like we have on the current wiki
