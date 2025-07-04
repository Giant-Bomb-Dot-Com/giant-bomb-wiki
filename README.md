# The Giant Bomb Wiki

We should add stuff here.

## Running the wiki for the first time

- Have docker desktop installed and running
- Run `docker compose up -d` in the root of this repo.
- Go to http://localhost:8080/ in your browser. The installer will run automatically on first start.
  - Select options and hit continue until you reach the "MediaWiki 1.43.1 installation" page.
  - The values you need for the database connection are in the docker-compose.yml file - "db" is your host, etc.
  - Hit continue on a few more pages until you need to name wiki.
  - Name the wiki and enter the username and password for the Administrator account
  - At the bottom of this page make sure to select "Ask me more questions." and hit continue
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
- You should now be able to access the wiki at http://localhost:8080/.
- You can visit http://localhost:8080/index.php/Special:Version to see the loaded features.
  - Skins
    - GiantBomb
    - Vector
  - Extensions
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

- You can disable/enable skins by editing the `LocalSettings.php` file. See https://www.mediawiki.org/wiki/Manual:LocalSettings.php
- To start working on the new Giant Bomb Skin add the following to your `LocalSettings.php` file:
  - `wfLoadSkin( 'GiantBomb' );`
  - `$wgDefaultSkin = "giantbomb";`
- Make sure your editor of choice is setup with [Prettier](https://prettier.io/docs/install) as a default formatter. We're relying on Prettier to enforce our [`.editorconfig`](https://editorconfig.org/) rules.

### Building Vue Components

#### Javascript Resource Module

- Vue components can be defined as a `.js` file using the Vue [Single File Component](https://vuejs.org/api/sfc-spec.html) syntax.
- Create new Vue Component in `/skins/GiantBomb/resources/components` as a `.js` file.
  - See `/skins/GiantBomb/resources/components/VueExampleComponent.js` as an example.
- Add component to `skin.json` as a separate Resource Module.
  - See `skin.giantbomb.vueexamplecomponent` for example.

#### Vue Single File Component

- Vue components can be defined as a `.vue` file using the Vue [Single File Component](https://vuejs.org/api/sfc-spec.html) syntax.
  - Supports styling component via the `<style>` tag.
- Create new Vue Component in `/skins/GiantBomb/resources/components` as a `.vue` file.
  - See `/skins/GiantBomb/resources/components/VueSingleFileComponentExample.vue` as an example.
- Add component to `skin.json` within the `skins.giantbomb` Resource Module as a `packageFile`.
  - See `skin.giantbomb` for example.

### Binding Vue Components

- To allow Vue Component to be bound to the DOM within a `.php` template, components must then be loaded via the components object in `/skins/GiantBomb/resources/components/index.js`.
- In any `.php` template use the attribute `data-vue-component=` on any DOM element.
  - See `/skins/GiantBomb/includes/GiantBombTemplate.php` as an example.
- Vue Component will be added to DOM as a child of that element.
- Props are fully functional by prefixing with `data-my-prop=` pattern, where `my-prop` is the name of your prop in kebab case, see `VueExampleComponent.js` for example.

#### Binding Vue Components within other Vue components.

- As long as the component has been included as per [Building Vue Components](#building-vue-components), it can be added to another Vue component via the `require` syntax.
  - See `/skins/GiantBomb/resources/components/VueSingleFileComponentExample.vue` as an example.

## SemanticMediaWiki

- Add more notes
- Can add SMW attributes test by going to: http://localhost:8080/index.php?title=The_Legend_of_Zelda:\_Twilight_Princess and creating page with the following:

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
